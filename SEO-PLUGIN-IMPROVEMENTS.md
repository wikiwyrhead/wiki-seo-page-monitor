# SEO Page Monitor Plugin - Comprehensive Improvement Plan

## Based on Forensic SEO Audit Session (December 4, 2025)

This document outlines improvements to make the SEO Page Monitor plugin perform audits as accurately as our Playwright-based forensic audits.

---

## 🔴 CRITICAL GAPS (High Priority)

### 1. Content Area Detection - NOT IMPLEMENTED

**Current State:**
The plugin analyzes the ENTIRE HTML page, including header, footer, sidebar, and navigation.

```php
// Current: Counts ALL links on page
private function count_internal_links($html, $base_url) {
    preg_match_all('/<a\s+[^>]*href=["\'](.*?)["\']/is', $html, $matches);
    // This includes header nav, footer links, sidebar widgets, etc.
}
```

**Problem:**
- Link counts are inflated (includes navigation, footer, sidebar)
- Image counts include logo, icons, decorative images
- Word count includes boilerplate text
- Heading analysis includes sidebar widget headings

**Solution - Add Content Area Detection:**

```php
/**
 * Extract main content area from HTML
 * Uses H1 as anchor and traverses up to find content container
 */
private function extract_content_area($html) {
    // Create DOMDocument for proper parsing
    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($dom);
    
    // Find the main H1
    $h1_nodes = $xpath->query('//h1');
    if ($h1_nodes->length === 0) {
        return $html; // Fallback to full HTML
    }
    
    $h1 = $h1_nodes->item(0);
    $container = $h1->parentNode;
    
    // Traverse up to find a container with substantial content
    while ($container && $container->nodeName !== 'body') {
        $paragraphs = $xpath->query('.//p', $container)->length;
        $images = $xpath->query('.//img', $container)->length;
        $headings = $xpath->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $container)->length;
        
        // Check if this container has substantial content
        if ($paragraphs >= 5 && $images >= 1 && $headings >= 3) {
            // Exclude whole-page containers
            $class = $container->getAttribute('class');
            if (stripos($class, 'site') === false && $container->nodeName !== 'body') {
                break;
            }
        }
        $container = $container->parentNode;
    }
    
    // Return only the content area HTML
    return $dom->saveHTML($container);
}
```

---

### 2. Keyword Density Analysis - NOT IMPLEMENTED

**Current State:**
The plugin does NOT calculate keyword density at all.

**Solution - Add Keyword Density Calculation:**

```php
/**
 * Calculate keyword density in content
 */
private function calculate_keyword_density($content, $keyword) {
    if (empty($keyword) || empty($content)) {
        return array(
            'count' => 0,
            'density' => '0%',
            'status' => 'missing'
        );
    }
    
    // Clean content
    $text = strtolower(strip_tags($content));
    $keyword = strtolower(trim($keyword));
    
    // Count words
    $words = str_word_count($text);
    if ($words === 0) return array('count' => 0, 'density' => '0%', 'status' => 'error');
    
    // Count keyword occurrences (as phrase)
    $keyword_count = substr_count($text, $keyword);
    
    // Calculate density
    $density = ($keyword_count / $words) * 100;
    
    // Determine status
    $status = 'good';
    if ($density < 0.5) {
        $status = 'low';
    } elseif ($density > 3) {
        $status = 'high';
    }
    
    return array(
        'count' => $keyword_count,
        'density' => round($density, 1) . '%',
        'status' => $status,
        'words' => $words
    );
}
```

**Add to analyze_seo() function:**

```php
// In analyze_seo() method, add:
$keyword = $analysis['focusKeyword'];
$content_area = $this->extract_content_area($html);
$keyword_analysis = $this->calculate_keyword_density($content_area, $keyword);

$analysis['keywordDensity'] = $keyword_analysis['density'];
$analysis['keywordCount'] = $keyword_analysis['count'];
$analysis['technicalSeo']['keywordDensity'] = $keyword_analysis['density'];

// Add SEO hints based on density
if ($keyword_analysis['status'] === 'low') {
    $analysis['seoHints'][] = "⚠️ Keyword density too low ({$keyword_analysis['density']}) - aim for 0.8-2.5%";
} elseif ($keyword_analysis['status'] === 'high') {
    $analysis['seoHints'][] = "⚠️ Keyword density too high ({$keyword_analysis['density']}) - reduce to avoid keyword stuffing";
} else {
    $analysis['seoHints'][] = "✅ Keyword density optimal ({$keyword_analysis['density']})";
}
```

---

### 3. Enhanced Image SEO Analysis - PARTIALLY IMPLEMENTED

**Current State:**
Only checks if alt text exists or is missing.

```php
// Current: Basic alt check
private function check_alt_images($html) {
    // Only counts missing alt text
    // Does NOT check keyword presence in alt text
}
```

**Solution - Enhanced Image Analysis:**

```php
/**
 * Comprehensive image SEO analysis
 */
private function analyze_images_seo($html, $keyword) {
    $content_area = $this->extract_content_area($html);
    
    preg_match_all('/<img\s+[^>]*>/is', $content_area, $img_matches);
    $total_images = count($img_matches[0]);
    
    if ($total_images === 0) {
        return array(
            'total' => 0,
            'missing_alt' => 0,
            'with_keyword' => 0,
            'details' => array(),
            'status' => 'No images'
        );
    }
    
    $missing_alt = 0;
    $with_keyword = 0;
    $details = array();
    
    foreach ($img_matches[0] as $index => $img_tag) {
        // Extract src
        preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $src_match);
        $src = isset($src_match[1]) ? basename($src_match[1]) : 'unknown';
        
        // Extract alt
        preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match);
        $alt = isset($alt_match[1]) ? $alt_match[1] : '';
        
        // Check alt status
        $has_alt = !empty(trim($alt));
        $has_keyword = !empty($keyword) && stripos($alt, $keyword) !== false;
        
        if (!$has_alt) {
            $missing_alt++;
        }
        if ($has_keyword) {
            $with_keyword++;
        }
        
        $details[] = array(
            'id' => $index + 1,
            'src' => $src,
            'alt' => $alt ?: '❌ MISSING',
            'has_keyword' => $has_keyword
        );
    }
    
    return array(
        'total' => $total_images,
        'missing_alt' => $missing_alt,
        'with_keyword' => $with_keyword,
        'details' => $details,
        'status' => $missing_alt > 0 ? "Missing {$missing_alt}" : 'Complete'
    );
}
```

---

### 4. First 100 Words Keyword Check - NOT IMPLEMENTED

**Current State:**
Does not check if keyword appears in opening paragraph.

**Solution:**

```php
/**
 * Check if keyword appears in first 100 words
 */
private function check_opening_paragraph($html, $keyword) {
    if (empty($keyword)) {
        return array('found' => false, 'status' => 'No keyword set');
    }
    
    $content_area = $this->extract_content_area($html);
    $text = strtolower(strip_tags($content_area));
    $words = preg_split('/\s+/', $text);
    $first_100 = implode(' ', array_slice($words, 0, 100));
    
    $found = stripos($first_100, strtolower($keyword)) !== false;
    
    return array(
        'found' => $found,
        'status' => $found ? '✅ Keyword in opening paragraph' : '⚠️ Add keyword to first 100 words',
        'first_words' => $first_100
    );
}
```

---

### 5. FAQ Section Detection - NOT IMPLEMENTED

**Current State:**
Does not detect FAQ sections for rich snippet opportunities.

**Solution:**

```php
/**
 * Detect FAQ section presence
 */
private function detect_faq_section($html) {
    // Check for FAQ heading
    $has_faq_heading = preg_match('/<h[2-4][^>]*>.*?(FAQ|Frequently Asked|Questions).*?<\/h[2-4]>/is', $html);
    
    // Check for FAQ schema
    $has_faq_schema = stripos($html, '"@type":"FAQPage"') !== false || 
                      stripos($html, '"@type": "FAQPage"') !== false;
    
    // Check for question-answer pattern
    $qa_pattern = preg_match_all('/<(dt|h[3-5])[^>]*>.*?\?.*?<\/(dt|h[3-5])>/is', $html);
    
    return array(
        'has_faq_heading' => $has_faq_heading > 0,
        'has_faq_schema' => $has_faq_schema,
        'question_count' => $qa_pattern,
        'status' => $has_faq_schema ? '✅ FAQ Schema present' : 
                   ($has_faq_heading ? '⚠️ Add FAQ Schema markup' : '❌ No FAQ section')
    );
}
```

---

## 🟡 MEDIUM PRIORITY IMPROVEMENTS

### 6. Heading Keyword Analysis - PARTIALLY IMPLEMENTED

**Current State:**
Counts headings but doesn't check keyword presence in each.

**Solution - Enhanced Heading Analysis:**

```php
/**
 * Analyze headings with keyword presence
 */
private function analyze_headings_seo($html, $keyword) {
    $content_area = $this->extract_content_area($html);
    $headings = array();
    
    for ($i = 1; $i <= 6; $i++) {
        preg_match_all("/<h{$i}[^>]*>(.*?)<\/h{$i}>/is", $content_area, $matches);
        foreach ($matches[1] as $text) {
            $clean_text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
            $has_keyword = !empty($keyword) && stripos($clean_text, $keyword) !== false;
            $headings[] = array(
                'tag' => "H{$i}",
                'text' => trim($clean_text),
                'has_keyword' => $has_keyword
            );
        }
    }
    
    $with_keyword = count(array_filter($headings, function($h) { return $h['has_keyword']; }));
    
    return array(
        'headings' => $headings,
        'total' => count($headings),
        'with_keyword' => $with_keyword,
        'h1_count' => count(array_filter($headings, function($h) { return $h['tag'] === 'H1'; }))
    );
}
```

---

### 7. Content-Only Link Analysis - NOT IMPLEMENTED

**Current State:**
Counts ALL links on page including navigation.

**Solution:**

```php
/**
 * Count links in content area only
 */
private function count_content_links($html, $base_url) {
    $content_area = $this->extract_content_area($html);
    $domain = parse_url($base_url, PHP_URL_HOST);
    
    preg_match_all('/<a\s+[^>]*href=["\'](.*?)["\']/is', $content_area, $matches);
    
    $internal = 0;
    $external = 0;
    
    foreach ($matches[1] as $href) {
        if (strpos($href, 'http') === 0) {
            $href_domain = parse_url($href, PHP_URL_HOST);
            if ($href_domain === $domain) {
                $internal++;
            } else {
                $external++;
            }
        } elseif (strpos($href, '/') === 0 || strpos($href, '#') !== 0) {
            $internal++;
        }
    }
    
    return array(
        'internal' => $internal,
        'external' => $external,
        'total' => $internal + $external
    );
}
```

---

### 8. SEO Score Calculator - NEEDS IMPROVEMENT

**Current State:**
Basic scoring based on element presence.

**Solution - Weighted Scoring System:**

```php
/**
 * Calculate comprehensive SEO score
 */
private function calculate_seo_score($analysis) {
    $score = 0;
    $max_score = 100;
    
    // Title (15 points)
    $title_len = strlen($analysis['title']);
    if ($title_len >= 50 && $title_len <= 60) {
        $score += 15;
    } elseif ($title_len >= 30 && $title_len <= 70) {
        $score += 10;
    } elseif ($title_len > 0) {
        $score += 5;
    }
    
    // Meta Description (10 points)
    $desc_len = strlen($analysis['description']);
    if ($desc_len >= 150 && $desc_len <= 160) {
        $score += 10;
    } elseif ($desc_len >= 100 && $desc_len <= 180) {
        $score += 7;
    } elseif ($desc_len > 0) {
        $score += 3;
    }
    
    // H1 Tag (15 points)
    if (isset($analysis['headingAnalysis'])) {
        if ($analysis['headingAnalysis']['h1_count'] === 1) {
            $score += 10;
            if ($analysis['headingAnalysis']['with_keyword'] > 0) {
                $score += 5;
            }
        }
    }
    
    // Keyword Density (15 points)
    if (isset($analysis['keywordDensity'])) {
        $density = floatval($analysis['keywordDensity']);
        if ($density >= 0.8 && $density <= 2.5) {
            $score += 15;
        } elseif ($density >= 0.5 && $density <= 3) {
            $score += 10;
        } elseif ($density > 0) {
            $score += 5;
        }
    }
    
    // Opening Paragraph (10 points)
    if (isset($analysis['openingParagraph']) && $analysis['openingParagraph']['found']) {
        $score += 10;
    }
    
    // Image Alt Text (10 points)
    if (isset($analysis['imageAnalysis'])) {
        if ($analysis['imageAnalysis']['missing_alt'] === 0) {
            $score += 5;
        }
        if ($analysis['imageAnalysis']['with_keyword'] > 0) {
            $score += 5;
        }
    }
    
    // Internal Links (5 points)
    $internal = intval($analysis['internalLinks']);
    if ($internal >= 3) {
        $score += 5;
    } elseif ($internal > 0) {
        $score += 2;
    }
    
    // External Links (5 points)
    $external = intval($analysis['externalLinks']);
    if ($external >= 1 && $external <= 5) {
        $score += 5;
    } elseif ($external > 0) {
        $score += 2;
    }
    
    // Content Length (5 points)
    $words = intval($analysis['technicalSeo']['wordCount'] ?? 0);
    if ($words >= 800) {
        $score += 5;
    } elseif ($words >= 500) {
        $score += 3;
    } elseif ($words >= 300) {
        $score += 1;
    }
    
    // Schema Markup (5 points)
    if (stripos(implode('', $analysis['seoHints']), 'Schema markup found') !== false) {
        $score += 5;
    }
    
    // FAQ Section (5 points)
    if (isset($analysis['faqAnalysis']) && $analysis['faqAnalysis']['has_faq_schema']) {
        $score += 5;
    }
    
    return array(
        'score' => $score,
        'max' => $max_score,
        'percentage' => round(($score / $max_score) * 100)
    );
}
```

---

## 🟢 LOW PRIORITY IMPROVEMENTS

### 9. Lazy-Load Image Detection

```php
/**
 * Detect lazy-loaded images that may need alt text
 */
private function detect_lazy_images($html) {
    // Base64 encoded placeholders
    preg_match_all('/<img[^>]+src=["\']data:image[^"\']*["\']/i', $html, $base64_matches);
    
    // Common lazy-load attributes
    preg_match_all('/<img[^>]+(data-src|data-lazy|loading="lazy")[^>]*>/i', $html, $lazy_matches);
    
    return array(
        'base64_placeholders' => count($base64_matches[0]),
        'lazy_loaded' => count($lazy_matches[0])
    );
}
```

### 10. HowTo Schema Detection

```php
/**
 * Detect HowTo content for schema opportunity
 */
private function detect_howto_content($html) {
    $has_howto_schema = stripos($html, '"@type":"HowTo"') !== false;
    $has_steps = preg_match('/(step\s*\d|how\s*to)/i', $html);
    $has_numbered_list = preg_match('/<ol[^>]*>.*?<li/is', $html);
    
    return array(
        'has_schema' => $has_howto_schema,
        'has_steps' => $has_steps > 0,
        'has_numbered_list' => $has_numbered_list > 0,
        'recommendation' => !$has_howto_schema && ($has_steps || $has_numbered_list) 
            ? '⚠️ Consider adding HowTo schema' : null
    );
}
```

---

## 📊 UPDATED DATA STRUCTURE

Add these fields to the page data structure:

```php
$page_data = array(
    // Existing fields...
    'url' => '',
    'title' => '',
    'description' => '',
    'focusKeyword' => '',
    'rankMathScore' => '',
    'internalLinks' => '',
    'externalLinks' => '',
    'altImages' => '',
    
    // NEW FIELDS
    'keywordDensity' => '',      // e.g., "1.5%"
    'keywordCount' => 0,         // e.g., 8
    'wordCount' => 0,            // e.g., 850
    'imagesWithKeyword' => 0,    // e.g., 2
    'imagesMissingAlt' => 0,     // e.g., 1
    'headingsWithKeyword' => 0,  // e.g., 3
    'keywordInOpening' => false, // true/false
    'hasFaqSection' => false,    // true/false
    'hasFaqSchema' => false,     // true/false
    'seoScore' => 0,             // 0-100
    
    // Detailed analysis
    'imageDetails' => array(),   // Array of image info
    'headingDetails' => array(), // Array of heading info
);
```

---

## 🔧 IMPLEMENTATION PRIORITY

### Phase 1 (Critical - Do First)
1. ✅ Content Area Detection
2. ✅ Keyword Density Calculation
3. ✅ Enhanced Image SEO Analysis
4. ✅ Content-Only Link Counting

### Phase 2 (Important)
5. ✅ First 100 Words Check
6. ✅ FAQ Section Detection
7. ✅ Heading Keyword Analysis
8. ✅ Improved SEO Score Calculator

### Phase 3 (Nice to Have)
9. ✅ Lazy-Load Image Detection
10. ✅ HowTo Schema Detection
11. ✅ Export improvements with new fields

---

## 📝 FRONTEND UPDATES NEEDED

Update `assets/js/app.jsx` to display new fields:

1. Add Keyword Density display in Overview tab
2. Add Image SEO breakdown panel
3. Add Heading analysis with keyword indicators
4. Add FAQ detection status
5. Update SEO Score display with weighted breakdown

---

## 🧪 TESTING CHECKLIST

After implementing changes, test with these scenarios:

- [ ] Page with keyword in title, description, H1, and content
- [ ] Page with missing alt text on images
- [ ] Page with keyword in image alt text
- [ ] Page with FAQ section (with and without schema)
- [ ] Page with low keyword density (<0.5%)
- [ ] Page with high keyword density (>3%)
- [ ] Page with keyword in first 100 words
- [ ] Page with keyword NOT in first 100 words
- [ ] Page with multiple H1 tags
- [ ] Page with proper heading hierarchy

---

**End of Improvement Plan**
