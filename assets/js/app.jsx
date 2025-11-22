import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';

// Lucide React icons - we'll use simple SVG substitutes
const Search = () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>;
const TrendingUp = () => <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>;
const ExternalLink = ({ size = 24 }) => <svg className={`w-${size/4} h-${size/4}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>;
const Edit2 = ({ size = 18 }) => <svg className={`w-${size/4} h-${size/4}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>;
const Trash2 = ({ size = 18 }) => <svg className={`w-${size/4} h-${size/4}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>;
const Zap = ({ size = 20 }) => <svg className={`w-${size/4} h-${size/4}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>;
const AlertCircle = ({ size = 48 }) => <svg className={`w-${size/4} h-${size/4}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>;
const HelpCircle = () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>;

// Tooltip component for field help
const FieldTooltip = ({ text }) => (
  <span 
    className="inline-flex items-center ml-2 text-blue-500 hover:text-blue-700 cursor-help"
    title={text}
    style={{ cursor: 'help' }}
  >
    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
      <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
    </svg>
  </span>
);

const SEOMonitor = () => {
  const [pages, setPages] = useState([]);
  const [filter, setFilter] = useState('All');
  const [searchTerm, setSearchTerm] = useState('');
  const [editingId, setEditingId] = useState(null);
  const [editForm, setEditForm] = useState({});
  const [activeTab, setActiveTab] = useState('overview');
  const [loading, setLoading] = useState(true);
  const [showAddForm, setShowAddForm] = useState(false);
  const [fetchingPage, setFetchingPage] = useState(false);
  const [testingPageSpeed, setTestingPageSpeed] = useState(null);
  const [exporting, setExporting] = useState(false);
  const [importing, setImporting] = useState(false);
  const [notification, setNotification] = useState(null);
  const [cardTabs, setCardTabs] = useState({});
  const [newPage, setNewPage] = useState({
    url: '',
    title: '',
    ranking: '',
    searchVolume: '',
    priority: 'Medium',
    focusKeyword: '',
    rankMathScore: '',
    pageSpeedMobile: '',
    pageSpeedDesktop: '',
    internalLinks: '',
    externalLinks: '',
    altImages: '',
    onPageActions: '',
    nextActions: '',
    recommendations: ''
  });
  const [openRecommendations, setOpenRecommendations] = useState({});
  const [openActions, setOpenActions] = useState({});

  const iconize = (text) => {
    const t = String(text || '').trim();
    const l = t.toLowerCase();
    if (/[✅⚠️❌🔗🖼📱⚡📖🎯🔍🔔📑🤖]/.test(t)) return t; // already has icon
    if (/missing|\bno\b|not found|set to noindex|error/.test(l)) return `❌ ${t}`;
    if (/low\b|warn|slow/.test(l)) return `⚠️ ${t}`;
    if (/good|ok\b|enabled|passed|success/.test(l)) return `✅ ${t}`;
    if (/link|external|internal/.test(l)) return `🔗 ${t}`;
    if (/image|alt/.test(l)) return `🖼 ${t}`;
    if (/mobile/.test(l)) return `📱 ${t}`;
    if (/speed|load|performance/.test(l)) return `⚡ ${t}`;
    if (/schema|faq|howto/.test(l)) return `📖 ${t}`;
    if (/keyword/.test(l)) return `🎯 ${t}`;
    if (/cta/.test(l)) return `🔔 ${t}`;
    if (/robot|robots|noindex/.test(l)) return `🤖 ${t}`;
    if (/h1|title|header/.test(l)) return `📑 ${t}`;
    return `• ${t}`;
  };

  // Expand a combined Actions Completed string into multiple bullets
  const expandActionItem = (text) => {
    const raw = String(text || '').trim();
    if (!raw) return [];
    // Normalize spaces for robust matching
    const norm = raw.replace(/\s+/g, ' ').trim();
    // Case 1: Combined header structure with H1 count and H1 content
    let m = norm.match(/^Header Structure:\s*H1\s*:\s*(\d+)\s*H1\s*Content\s*:\s*(.+)$/i);
    if (m) {
      return [
        iconize(`📑 Header Structure: H1:${m[1]}`),
        iconize(`📑 H1 Content: ${m[2]}`)
      ];
    }
    // Case 2: Starts directly with H1 count and content
    m = norm.match(/^H1\s*:\s*(\d+)\s*H1\s*Content\s*:\s*(.+)$/i);
    if (m) {
      return [
        iconize(`📑 H1:${m[1]}`),
        iconize(`📑 H1 Content: ${m[2]}`)
      ];
    }
    // Default: do not split further to avoid noisy extra rows
    return [iconize(raw)];
  };

  // Load pages from WordPress
  useEffect(() => {
    fetchPages();
  }, []);

  const fetchPages = async () => {
    try {
      const response = await fetch(`${seoMonitorData.restUrl}pages`, {
        headers: {
          'X-WP-Nonce': seoMonitorData.nonce
        }
      });
      const data = await response.json();
      // Always set the data, even if it's empty array
      const loadedPages = Array.isArray(data) ? data : [];
      setPages(loadedPages);

      // Fetch recommendations for pages that don't have them yet
      const pagesMissing = loadedPages
        .map((p, i) => ({ page: p, i }))
        .filter(item => !item.page.recommendations || item.page.recommendations === '');

      // limit the number of auto-fetches to avoid overload
      const limit = 6;
      for (let j = 0; j < Math.min(limit, pagesMissing.length); j++) {
        const { page, i } = pagesMissing[j];
        try {
          const recs = await getRecommendationsForUrl(page.url);
          if (recs && recs.length > 0) {
            const newPages = [...loadedPages];
            newPages[i] = { ...newPages[i], recommendations: recs.join('\n') };
            setPages(newPages);
            savePages(newPages);
          }
        } catch (err) {
          console.error('Failed to fetch recommendations for', page.url, err);
        }
      }
    } catch (error) {
      console.error('Error fetching pages:', error);
      setPages([]);
    } finally {
      setLoading(false);
    }
  };

  // CSV export helpers (admin-post.php)
  const exportCsvAll = () => {
    const url = `${seoMonitorData.adminUrl}admin-post.php?action=seo_monitor_export_csv&_wpnonce=${seoMonitorData.exportNonce}`;
    window.location.href = url;
  };

  const exportCsvOne = (pageUrl) => {
    if (!pageUrl) return;
    const url = `${seoMonitorData.adminUrl}admin-post.php?action=seo_monitor_export_csv&_wpnonce=${seoMonitorData.exportNonce}&url=${encodeURIComponent(pageUrl)}`;
    window.location.href = url;
  };

  // Excel export helpers (admin-post.php)
  const exportXlsxAll = () => {
    if (!seoMonitorData.xlsxAvailable) return;
    const url = `${seoMonitorData.adminUrl}admin-post.php?action=seo_monitor_export_xlsx&_wpnonce=${seoMonitorData.exportNonce}`;
    window.location.href = url;
  };
  const exportXlsxOne = (pageUrl) => {
    if (!seoMonitorData.xlsxAvailable || !pageUrl) return;
    const url = `${seoMonitorData.adminUrl}admin-post.php?action=seo_monitor_export_xlsx&_wpnonce=${seoMonitorData.exportNonce}&url=${encodeURIComponent(pageUrl)}`;
    window.location.href = url;
  };

  const getRecommendationsForUrl = async (url) => {
    if (!url || !url.startsWith('http')) return [];
    try {
      const response = await fetch(`${seoMonitorData.restUrl}fetch-page`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': seoMonitorData.nonce
        },
        body: JSON.stringify({ url })
      });
      const data = await response.json();
      if (data && data.recommendations) {
        return Array.isArray(data.recommendations) ? data.recommendations : [];
      }
    } catch (error) {
      console.error('Error fetching recommendations:', error);
    }
    return [];
  };

  const savePages = async (updatedPages) => {
    try {
      await fetch(`${seoMonitorData.restUrl}pages`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': seoMonitorData.nonce
        },
        body: JSON.stringify(updatedPages)
      });
    } catch (error) {
      console.error('Error saving pages:', error);
    }
  };

  const updatePage = (index, field, value) => {
    const newPages = [...pages];
    newPages[index] = { ...newPages[index], [field]: value };
    setPages(newPages);
    savePages(newPages);
  };

  const deletePage = (index) => {
    if (window.confirm('Remove this page?')) {
      const newPages = pages.filter((_, i) => i !== index);
      setPages(newPages);
      savePages(newPages);
    }
  };

  const startEdit = (index) => {
    setEditingId(index);
    setEditForm(pages[index]);
  };

  const saveEdit = (index) => {
    const newPages = [...pages];
    newPages[index] = editForm;
    setPages(newPages);
    savePages(newPages);
    setEditingId(null);
  };

  const fetchPageData = async (url, isEditMode = false) => {
    if (!url || !url.startsWith('http')) {
      alert('Please enter a valid URL');
      return;
    }

    setFetchingPage(true);
    
    try {
      const response = await fetch(`${seoMonitorData.restUrl}fetch-page`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': seoMonitorData.nonce
        },
        body: JSON.stringify({ url })
      });
      
      const data = await response.json();
      
      if (data.success) {
        // Format SEO hints
        const seoHints = data.seoAnalysis ? data.seoAnalysis.join('\n') : '';
        
        // Format technical SEO info in organized sections
        const techSections = [];
        
        if (data.technicalSeo) {
          // Header Structure Section
          if (data.technicalSeo.headers) {
            let headerInfo = `📑 Header Structure: ${data.technicalSeo.headers}`;
            if (data.technicalSeo.h1Text) {
              headerInfo += `\n   H1 Content: "${data.technicalSeo.h1Text}"`;
            }
            techSections.push(headerInfo);
          }
          
          // Content Section
          const contentInfo = [];
          if (data.technicalSeo.wordCount) contentInfo.push(`Words: ${data.technicalSeo.wordCount}`);
          if (data.technicalSeo.images) contentInfo.push(`${data.technicalSeo.images}`);
          if (contentInfo.length > 0) {
            techSections.push(`📝 Content: ${contentInfo.join(' | ')}`);
          }
          
          // SEO Tags Section
          const seoTags = [];
          if (data.technicalSeo.canonical) {
            const shortCanonical = data.technicalSeo.canonical.length > 50 
              ? data.technicalSeo.canonical.substring(0, 47) + '...'
              : data.technicalSeo.canonical;
            seoTags.push(`Canonical: ${shortCanonical}`);
          }
          if (data.technicalSeo.schema) seoTags.push(`Schema: ${data.technicalSeo.schema}`);
          if (seoTags.length > 0) {
            techSections.push(`🏷️ SEO Tags: ${seoTags.join(' | ')}`);
          }
          
          // Indexing Section
          if (data.technicalSeo.robots) {
            techSections.push(`🤖 Robots: ${data.technicalSeo.robots}`);
          }
        }
        
        const updatedData = {
          url: url,
          title: data.title || '',
          focusKeyword: data.focusKeyword || '',
          rankMathScore: data.rankMathScore || '',
          internalLinks: data.internalLinks || '',
          externalLinks: data.externalLinks || '',
          altImages: data.altImages || '',
          onPageActions: techSections.join('\n'),
          nextActions: seoHints,
          recommendations: data.recommendations ? data.recommendations.join('\n') : '',
          postId: data.postId || ''
        };

        if (isEditMode) {
          setEditForm({
            ...editForm,
            ...updatedData
          });
        } else {
          setNewPage({
            ...newPage,
            ...updatedData
          });
        }
      } else {
        alert('Could not fetch page data. Please enter details manually.');
      }
    } catch (error) {
      console.error('Error fetching page:', error);
      alert('Error fetching page data. Please enter details manually.');
    } finally {
      setFetchingPage(false);
    }
  };

  const debugRankMath = async (url) => {
    if (!url || !url.startsWith('http')) {
      alert('Please enter a valid URL');
      return;
    }

    try {
      const response = await fetch(`${seoMonitorData.restUrl}debug-meta`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': seoMonitorData.nonce
        },
        body: JSON.stringify({ url })
      });
      
      const data = await response.json();
      
      if (data.success) {
        console.log('=== RankMath Debug Info ===');
        console.log('Post ID:', data.post_id);
        console.log('Post Title:', data.post_title);
        console.log('Post Type:', data.post_type);
        console.log('Post Status:', data.post_status);
        console.log('RankMath Meta Keys Found:', data.rankmath_meta_count);
        console.log('RankMath Meta Data:', data.rankmath_meta);
        console.log('Total Meta Keys:', data.total_meta_count);
        console.log('Sample Meta Keys:', data.sample_meta_keys);
        console.log('==========================');
        
        let message = `✅ Post Found!\n\n`;
        message += `Post ID: ${data.post_id}\n`;
        message += `Title: ${data.post_title}\n`;
        message += `Type: ${data.post_type}\n`;
        message += `RankMath Keys: ${data.rankmath_meta_count}\n\n`;
        
        if (data.rankmath_meta_count > 0) {
          message += `RankMath Data Found:\n`;
          for (const [key, value] of Object.entries(data.rankmath_meta)) {
            message += `  ${key}: ${value}\n`;
          }
        } else {
          message += `⚠️ No RankMath meta data found for this post.\n`;
          message += `This might mean RankMath is not configured for this page.`;
        }
        
        message += `\n\nFull details in browser console (F12)`;
        
        alert(message);
      } else {
        console.error('Debug failed:', data);
        alert(`❌ ${data.message}\n\nAttempted slug: ${data.slug_attempted || 'none'}\nParsed path: ${data.parsed_path || 'none'}\n\nThis URL might not be a WordPress post/page.`);
      }
    } catch (error) {
      console.error('Error debugging:', error);
      alert('Error debugging page data.');
    }
  };

  const runPageSpeedTest = async (pageIndex) => {
    const page = pages[pageIndex];
    setTestingPageSpeed(pageIndex);

    try {
      const response = await fetch(`${seoMonitorData.restUrl}pagespeed`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': seoMonitorData.nonce
        },
        body: JSON.stringify({ url: page.url })
      });
      
      const data = await response.json();
      
      if (response.ok && data.mobile_score !== null && data.desktop_score !== null) {
        const updatedPages = [...pages];
        updatedPages[pageIndex] = {
          ...page,
          pageSpeedMobile: data.mobile_score,
          pageSpeedDesktop: data.desktop_score,
          pageSpeedMobileUrl: data.mobile_url,
          pageSpeedDesktopUrl: data.desktop_url,
          pageSpeedTestedAt: data.tested_at
        };
        setPages(updatedPages);
        
        await fetch(`${seoMonitorData.restUrl}pages`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': seoMonitorData.nonce
          },
          body: JSON.stringify(updatedPages)
        });
        
        alert(`✅ PageSpeed Test Complete!\n\nMobile: ${data.mobile_score}/100\nDesktop: ${data.desktop_score}/100`);
      } else {
        const errorMsg = data.message || data.error || 'Failed to fetch PageSpeed scores';
        console.error('PageSpeed API Error:', data);
        alert(`❌ PageSpeed Test Failed\n\n${errorMsg}\n\nPlease try again. The Google PageSpeed API may be temporarily unavailable or rate limited.`);
      }
    } catch (error) {
      console.error('Error testing PageSpeed:', error);
      alert('❌ Error running PageSpeed test.\n\nPlease check your internet connection and try again.');
    } finally {
      setTestingPageSpeed(null);
    }
  };

  const exportPages = async () => {
    setExporting(true);
    try {
      const response = await fetch(`${seoMonitorData.restUrl}export`, {
        headers: {
          'X-WP-Nonce': seoMonitorData.nonce
        }
      });
      const data = await response.json();
      
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `seo-monitor-export-${new Date().toISOString().split('T')[0]}.json`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
      
      showNotification('✅ Export successful! Check your downloads.', 'success');
    } catch (error) {
      console.error('Error exporting:', error);
      showNotification('❌ Export failed. Please try again.', 'error');
    } finally {
      setExporting(false);
    }
  };

  const importPages = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    setImporting(true);
    try {
      const text = await file.text();
      const data = JSON.parse(text);
      
      if (!data.pages || !Array.isArray(data.pages)) {
        throw new Error('Invalid file format');
      }

      const merge = window.confirm(
        `Import ${data.pages.length} pages?\n\n` +
        `Click OK to MERGE with existing pages\n` +
        `Click Cancel to REPLACE all pages`
      );

      const response = await fetch(`${seoMonitorData.restUrl}import`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': seoMonitorData.nonce
        },
        body: JSON.stringify({
          pages: data.pages,
          merge: merge
        })
      });

      const result = await response.json();
      
      if (result.success) {
        await fetchPages();
        showNotification(
          `✅ Imported ${result.imported_count} pages successfully!`,
          'success'
        );
      } else {
        throw new Error(result.message || 'Import failed');
      }
    } catch (error) {
      console.error('Error importing:', error);
      showNotification(
        `❌ Import failed: ${error.message}`,
        'error'
      );
    } finally {
      setImporting(false);
      event.target.value = ''; // Reset file input
    }
  };

  const showNotification = (message, type = 'info') => {
    setNotification({ message, type });
    setTimeout(() => setNotification(null), 5000);
  };

  const addNewPage = () => {
    if (!newPage.url || !newPage.title) {
      alert('Please enter at least a URL and Title');
      return;
    }
    
    const updatedPages = [...pages, { ...newPage, lastChecked: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }];
    setPages(updatedPages);
    savePages(updatedPages);
    
    // Reset form
    setNewPage({
      url: '',
      title: '',
      ranking: '',
      searchVolume: '',
      priority: 'Medium',
      focusKeyword: '',
      rankMathScore: '',
      pageSpeedMobile: '',
      pageSpeedDesktop: '',
      internalLinks: '',
      externalLinks: '',
      altImages: '',
      onPageActions: '',
      nextActions: '',
      recommendations: ''
    });
    setShowAddForm(false);
  };

  const filteredPages = pages.filter(page => {
    const matchesFilter = filter === 'All' || page.priority === filter;
    const matchesSearch = page.url.toLowerCase().includes(searchTerm.toLowerCase()) || 
                         page.title.toLowerCase().includes(searchTerm.toLowerCase());
    return matchesFilter && matchesSearch;
  });

  const stats = {
    total: pages.length,
    critical: pages.filter(p => p.priority === 'Critical').length,
    high: pages.filter(p => p.priority === 'High').length,
    ranking: pages.filter(p => p.ranking && p.ranking.trim() !== '' && p.ranking.toLowerCase() !== 'not ranking').length,
    needsPageSpeed: pages.filter(p => !p.pageSpeedMobile).length
  };

  const priorityColor = {
    Critical: 'bg-red-600 text-white',
    High: 'bg-orange-100 text-orange-800',
    Medium: 'bg-yellow-100 text-yellow-800',
    Low: 'bg-green-100 text-green-800'
  };

  const formatVolume = (vol) => {
    if (!vol) return '-';
    const num = parseInt(vol);
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
  };

  const parseRecommendations = (input) => {
    if (!input) return [];
    if (Array.isArray(input)) return input.filter(Boolean);
    let text = String(input || '').trim();
    if (!text) return [];

    // First split by explicit newlines
    let items = text.split(/\r?\n/).map(i => i.trim()).filter(Boolean);
    if (items.length > 1) return items;

    // Split by bullet characters
    items = text.split(/\s*•\s+/).map(i => i.trim()).filter(Boolean);
    if (items.length > 1) return items;

    // Try splitting by common emoji markers used in recommendations
    const emojiSplit = text.split(/(?=(?:📝|✅|🔗|🖼|📱|⚡|📖|🎯|🔍|🔔|💡|⚠️|🔧|🤖|✖|✔|❌|✅))/).map(i => i.trim()).filter(Boolean);
    if (emojiSplit.length > 1) return emojiSplit;

    // Generic emoji split (Extended Pictographic)
    try {
      const genericEmojiSplit = text.split(/(?=\p{Extended_Pictographic})/u).map(i => i.trim()).filter(Boolean);
      if (genericEmojiSplit.length > 1) return genericEmojiSplit;
    } catch (_) { /* Unicode property may not be supported */ }

    // Label splits for common technical phrases (helps Actions Completed)
    const techLabels = text.split(/(?=\b(Header Structure:|Content:|Robots:|H1\s*Content:|H1:)\b)/).map(i => i.trim()).filter(Boolean);
    if (techLabels.length > 1) return techLabels;

    // Try splitting by capitalized label keywords (TITLE:, META:, CONTENT:, LINKS:, IMAGES:, MOBILE:, SPEED:, READABILITY:, KEYWORD:)
    const labelSplit = text.split(/(?=(?:TITLE:|META:|CONTENT:|LINKS:|IMAGES:|MOBILE:|SPEED:|READABILITY:|KEYWORD:|CTA:|SCHEMA:))/).map(i => i.trim()).filter(Boolean);
    if (labelSplit.length > 1) return labelSplit;

    // Finally split into sentences as fallback
    const sentenceSplit = text.split(/(?<=[.!?])\s+(?=[A-Z0-9\u00C0-\u017F])/).map(i => i.trim()).filter(Boolean);
    if (sentenceSplit.length > 1) return sentenceSplit;

    return [text];
  };

  if (loading) {
    return <div className="p-6">Loading...</div>;
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 p-6">
      {notification && (
        <div className={`fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg ${
          notification.type === 'success' ? 'bg-green-500' : 
          notification.type === 'error' ? 'bg-red-500' : 'bg-blue-500'
        } text-white font-medium animate-fade-in`}>
          {notification.message}
        </div>
      )}
      
      <div className="max-w-7xl mx-auto">
        <div className="bg-white rounded-lg shadow-lg p-6 mb-6">
          <div className="flex items-center justify-between mb-6">
            <div>
              <h1 className="text-3xl font-bold text-gray-800 flex items-center gap-2">
                <TrendingUp />
                SEO Page Monitor & Optimizer
              </h1>
              <p className="text-gray-600 mt-1">Track Rankings & PageSpeed</p>
            </div>
            <div className="flex gap-2">
              <button
                onClick={exportPages}
                disabled={exporting || pages.length === 0}
                className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {exporting ? '⏳' : '📥'} Export JSON
              </button>
              <button
                onClick={exportCsvAll}
                disabled={pages.length === 0}
                className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                title="Export all pages to CSV (Excel compatible)"
              >
                📑 Export CSV
              </button>
              {seoMonitorData.xlsxAvailable && (
                <button
                  onClick={exportXlsxAll}
                  disabled={pages.length === 0}
                  className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                  title="Export all pages to Excel with multiple sheets"
                >
                  📊 Export Excel
                </button>
              )}
              <label className="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium flex items-center gap-2 cursor-pointer">
                <input
                  type="file"
                  accept=".json"
                  onChange={importPages}
                  disabled={importing}
                  className="hidden"
                />
                {importing ? '⏳' : '📤'} Import
              </label>
              <button
                onClick={() => setShowAddForm(!showAddForm)}
                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium flex items-center gap-2"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                </svg>
                Add New Page
              </button>
            </div>
          </div>

          <div className="grid grid-cols-5 gap-4 mb-6">
            <div className="bg-blue-600 rounded-lg p-4 text-white">
              <div className="text-2xl font-bold">{stats.total}</div>
              <div className="text-blue-100 text-sm">Total Pages</div>
            </div>
            <div className="bg-red-600 rounded-lg p-4 text-white">
              <div className="text-2xl font-bold">{stats.critical}</div>
              <div className="text-red-100 text-sm">Critical</div>
            </div>
            <div className="bg-orange-600 rounded-lg p-4 text-white">
              <div className="text-2xl font-bold">{stats.high}</div>
              <div className="text-orange-100 text-sm">High Priority</div>
            </div>
            <div className="bg-green-600 rounded-lg p-4 text-white">
              <div className="text-2xl font-bold">{stats.ranking}</div>
              <div className="text-green-100 text-sm">Ranking</div>
            </div>
            <div className="bg-purple-600 rounded-lg p-4 text-white">
              <div className="text-2xl font-bold">{stats.needsPageSpeed}</div>
              <div className="text-purple-100 text-sm">Need Test</div>
            </div>
          </div>

          <div className="bg-blue-50 border-l-4 border-blue-400 p-4 rounded mb-6">
            <h4 className="font-semibold text-blue-900 mb-2 flex items-center gap-2">
              <span>💡</span> Priority Levels Guide
            </h4>
            <div className="text-sm text-gray-700 grid grid-cols-2 gap-3">
              <div>
                <span className="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-red-600 text-white mr-2">Critical</span>
                <span>Urgent pages requiring immediate attention</span>
              </div>
              <div>
                <span className="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 mr-2">High</span>
                <span>Important pages needing prompt optimization</span>
              </div>
              <div>
                <span className="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 mr-2">Medium</span>
                <span>Standard pages for regular monitoring</span>
              </div>
              <div>
                <span className="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 mr-2">Low</span>
                <span>Lower priority pages for periodic review</span>
              </div>
            </div>
            <p className="text-xs text-gray-600 mt-2">
              💡 <strong>Tip:</strong> Set priority when adding/editing pages to organize your SEO workflow
            </p>
          </div>

          <div className="flex gap-4 mb-6">
            <div className="flex-1 relative">
              <div className="absolute left-5 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none" aria-hidden="true">
                <Search />
              </div>
              <input
                type="text"
                placeholder="Search pages..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="seo-monitor-search-input w-full pl-12 sm:pl-16 md:pl-20 pr-4 py-2 border border-gray-300 rounded-lg"
              />
            </div>
            <select
              value={filter}
              onChange={(e) => setFilter(e.target.value)}
              className="px-4 py-2 border border-gray-300 rounded-lg"
            >
              <option>All</option>
              <option>Critical</option>
              <option>High</option>
              <option>Medium</option>
              <option>Low</option>
            </select>
          </div>

          <div className="flex gap-2 border-b border-gray-200">
            <button
              onClick={() => setActiveTab('overview')}
              className={`px-4 py-2 font-medium ${activeTab === 'overview' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600'}`}
            >
              Overview
            </button>
            <button
              onClick={() => setActiveTab('technical')}
              className={`px-4 py-2 font-medium ${activeTab === 'technical' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600'}`}
            >
              Technical
            </button>
            <button
              onClick={() => setActiveTab('performance')}
              className={`px-4 py-2 font-medium ${activeTab === 'performance' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600'}`}
            >
              PageSpeed
            </button>
          </div>
        </div>

        {showAddForm && (
          <div className="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 className="text-xl font-bold mb-4 text-gray-800">Add New Page to Monitor</h2>
            
            <div className="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
              <p className="text-sm text-gray-700">
                <strong>Tip:</strong> Enter a URL and click "Fetch Page Data" to automatically pull title, meta info, and SEO data from the page.
              </p>
            </div>

            <div className="mb-4">
              <label className="block text-sm font-medium mb-1 text-gray-700 flex items-center">
                Page URL *
                <FieldTooltip text="Enter the full URL of the page you want to monitor. The system will automatically fetch SEO data from this URL." />
              </label>
              <div className="flex gap-2">
                <input
                  type="text"
                  value={newPage.url}
                  onChange={(e) => setNewPage({ ...newPage, url: e.target.value })}
                  placeholder="https://www.cmobile.com.au/page-name/"
                  className="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      fetchPageData(newPage.url);
                    }
                  }}
                />
                <button
                  onClick={() => fetchPageData(newPage.url)}
                  disabled={fetchingPage || !newPage.url}
                  className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed font-medium flex items-center gap-2 whitespace-nowrap"
                >
                  {fetchingPage ? (
                    <>
                      <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none"></circle>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      Fetching...
                    </>
                  ) : (
                    <>
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                      </svg>
                      Fetch Page Data
                    </>
                  )}
                </button>
                <button
                  onClick={() => debugRankMath(newPage.url)}
                  disabled={!newPage.url}
                  className="px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 disabled:bg-gray-400 disabled:cursor-not-allowed font-medium"
                  title="Debug RankMath data (check console)"
                >
                  🔍
                </button>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label className="block text-sm font-medium mb-1 text-gray-700 flex items-center">
                  Page Title *
                  <FieldTooltip text="The main title of your page. This is automatically fetched but you can edit it. Important for SEO and user experience." />
                </label>
                <input
                  type="text"
                  value={newPage.title}
                  onChange={(e) => setNewPage({ ...newPage, title: e.target.value })}
                  placeholder="Page Title (will be auto-filled)"
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1 text-gray-700 flex items-center">
                  Focus Keyword
                  <FieldTooltip text="The main keyword you're targeting for this page. Auto-detected from RankMath if available. Used to track ranking and optimization." />
                </label>
                <input
                  type="text"
                  value={newPage.focusKeyword}
                  onChange={(e) => setNewPage({ ...newPage, focusKeyword: e.target.value })}
                  placeholder="main keyword (auto-detected from RankMath)"
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
            </div>
            
            <div className="grid grid-cols-4 gap-4 mb-4">
              <div>
                <label className="block text-sm font-medium mb-1 text-gray-700 flex items-center">
                  Search Volume
                  <FieldTooltip text="Monthly search volume for your focus keyword. Get this from Google Keyword Planner, Ahrefs, or SEMrush. Helps prioritize pages." />
                </label>
                <input
                  type="text"
                  value={newPage.searchVolume}
                  onChange={(e) => setNewPage({ ...newPage, searchVolume: e.target.value })}
                  placeholder="5000"
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1 text-gray-700 flex items-center">
                  Priority
                  <FieldTooltip text="Set page importance: Critical (urgent), High (important), Medium (standard), Low (periodic review). Helps organize your SEO workflow." />
                </label>
                <select
                  value={newPage.priority}
                  onChange={(e) => setNewPage({ ...newPage, priority: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option>Low</option>
                  <option>Medium</option>
                  <option>High</option>
                  <option>Critical</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium mb-1 text-gray-700 flex items-center">
                  Current Ranking
                  <FieldTooltip text="Your page's current Google ranking position for the focus keyword (e.g., #5 or #25). Use 'Not ranking' if not in top 100." />
                </label>
                <input
                  type="text"
                  value={newPage.ranking}
                  onChange={(e) => setNewPage({ ...newPage, ranking: e.target.value })}
                  placeholder="#25 or Not ranking"
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1 text-gray-700 flex items-center">
                  RankMath Score
                  <FieldTooltip text="RankMath SEO score (0-100). Automatically fetched if RankMath plugin is installed. Higher scores indicate better on-page SEO." />
                </label>
                <input
                  type="text"
                  value={newPage.rankMathScore}
                  onChange={(e) => setNewPage({ ...newPage, rankMathScore: e.target.value })}
                  placeholder="85 (auto-filled)"
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
            </div>

            <div className="grid grid-cols-3 gap-4 mb-4">
              <div>
                <label className="block text-sm font-medium mb-1 text-gray-700 flex items-center">
                  Internal Links
                  <FieldTooltip text="Number of internal links on this page pointing to other pages on your site. Automatically counted when fetching. Good for site navigation and SEO." />
                </label>
                <input
                  type="text"
                  value={newPage.internalLinks}
                  onChange={(e) => setNewPage({ ...newPage, internalLinks: e.target.value })}
                  placeholder="Auto-counted"
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1 text-gray-700 flex items-center">
                  External Links
                  <FieldTooltip text="Number of outbound links to external websites. Automatically counted when fetching. Links to authoritative sources can boost credibility." />
                </label>
                <input
                  type="text"
                  value={newPage.externalLinks}
                  onChange={(e) => setNewPage({ ...newPage, externalLinks: e.target.value })}
                  placeholder="Auto-counted"
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1 text-gray-700 flex items-center">
                  Alt Images Status
                  <FieldTooltip text="Status of image alt attributes on this page. Shows 'Complete' if all images have alt text, or 'Missing X' if some are missing. Important for accessibility and SEO." />
                </label>
                <input
                  type="text"
                  value={newPage.altImages}
                  onChange={(e) => setNewPage({ ...newPage, altImages: e.target.value })}
                  placeholder="Auto-checked"
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium mb-1 text-gray-700 flex items-center">
                Next Actions
                <FieldTooltip text="SEO recommendations and action items automatically generated from page analysis. Shows what to improve (✅ Good, ⚠️ Warning, ❌ Issue). Edit to add your own notes." />
              </label>
              <textarea
                value={newPage.nextActions}
                onChange={(e) => setNewPage({ ...newPage, nextActions: e.target.value })}
                placeholder="SEO recommendations will appear here after fetching..."
                rows="4"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm"
              />
              <p className="text-xs text-gray-500 mt-1">✅ = Good, ⚠️ = Warning, ❌ = Issue</p>
            </div>

            <div>
              <label className="block text-sm font-medium mb-1 text-gray-700 flex items-center">
                Technical Info (Auto-filled)
                <FieldTooltip text="Technical SEO data automatically extracted from the page including: 📑 Header structure (H1-H6), 📝 Word count, 🏷️ Meta tags (canonical, robots, OG), 🤖 Indexing status, and schema markup." />
              </label>
              <textarea
                value={newPage.onPageActions}
                onChange={(e) => setNewPage({ ...newPage, onPageActions: e.target.value })}
                placeholder="Technical SEO data will appear here..."
                rows="5"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm font-mono bg-gray-50"
              />
              <p className="text-xs text-gray-500 mt-1">📑 Headers | 📝 Content | 🏷️ SEO Tags | 🤖 Indexing</p>
            </div>

            <div className="flex gap-2 justify-end mt-4">
              <button
                onClick={() => setShowAddForm(false)}
                className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-medium"
              >
                Cancel
              </button>
              <button
                onClick={addNewPage}
                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium"
              >
                Add Page
              </button>
            </div>
          </div>
        )}

        <div className="space-y-4">
          {filteredPages.map((page, index) => {
            const actualIndex = pages.indexOf(page);
            const perCardTab = cardTabs[actualIndex] || activeTab;
            const isEditing = editingId === actualIndex;

            return (
              <div key={actualIndex} className="bg-white rounded-lg shadow-md p-6">
                {isEditing ? (
                  <div className="space-y-4">
                    <div className="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                      <div className="flex items-center justify-between">
                        <p className="text-sm text-gray-700">
                          <strong>Tip:</strong> Click "Refetch Data" to update SEO information from the live page.
                        </p>
                        <button
                          onClick={() => fetchPageData(editForm.url, true)}
                          disabled={fetchingPage}
                          className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed font-medium flex items-center gap-2 whitespace-nowrap"
                        >
                          {fetchingPage ? (
                            <>
                              <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none"></circle>
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                              </svg>
                              Fetching...
                            </>
                          ) : (
                            <>
                              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                              </svg>
                              Refetch Data
                            </>
                          )}
                        </button>
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium mb-1 flex items-center">
                          Title
                          <FieldTooltip text="The main title of your page. Important for SEO and user experience." />
                        </label>
                        <input
                          type="text"
                          value={editForm.title}
                          onChange={(e) => setEditForm({ ...editForm, title: e.target.value })}
                          className="w-full px-3 py-2 border rounded-lg"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium mb-1 flex items-center">
                          Search Volume
                          <FieldTooltip text="Monthly search volume for your focus keyword. Get this from Google Keyword Planner, Ahrefs, or SEMrush. Helps prioritize pages." />
                        </label>
                        <input
                          type="text"
                          value={editForm.searchVolume || ''}
                          onChange={(e) => setEditForm({ ...editForm, searchVolume: e.target.value })}
                          className="w-full px-3 py-2 border rounded-lg"
                          placeholder="5000"
                        />
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium mb-1 flex items-center">
                          Ranking
                          <FieldTooltip text="Your page's current Google ranking position for the focus keyword (e.g., #5 or #25). Use 'Not ranking' if not in top 100." />
                        </label>
                        <input
                          type="text"
                          value={editForm.ranking || ''}
                          onChange={(e) => setEditForm({ ...editForm, ranking: e.target.value })}
                          className="w-full px-3 py-2 border rounded-lg"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium mb-1 flex items-center">
                          Priority
                          <FieldTooltip text="Set page importance: Critical (urgent), High (important), Medium (standard), Low (periodic review). Helps organize your SEO workflow." />
                        </label>
                        <select
                          value={editForm.priority}
                          onChange={(e) => setEditForm({ ...editForm, priority: e.target.value })}
                          className="w-full px-3 py-2 border rounded-lg"
                        >
                          <option>Low</option>
                          <option>Medium</option>
                          <option>High</option>
                          <option>Critical</option>
                        </select>
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium mb-1 flex items-center">
                          Mobile Score
                          <FieldTooltip text="Google PageSpeed score for mobile devices (0-100). Higher is better. Manually enter if Auto Test isn't working." />
                        </label>
                        <input
                          type="text"
                          value={editForm.pageSpeedMobile || ''}
                          onChange={(e) => setEditForm({ ...editForm, pageSpeedMobile: e.target.value })}
                          className="w-full px-3 py-2 border rounded-lg"
                          placeholder="85"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium mb-1 flex items-center">
                          Desktop Score
                          <FieldTooltip text="Google PageSpeed score for desktop devices (0-100). Higher is better. Manually enter if Auto Test isn't working." />
                        </label>
                        <input
                          type="text"
                          value={editForm.pageSpeedDesktop || ''}
                          onChange={(e) => setEditForm({ ...editForm, pageSpeedDesktop: e.target.value })}
                          className="w-full px-3 py-2 border rounded-lg"
                          placeholder="92"
                        />
                      </div>
                    </div>
                    <div>
                      <label className="block text-sm font-medium mb-1 flex items-center">
                        Next Actions
                        <FieldTooltip text="SEO recommendations and action items. Automatically generated based on page analysis. Edit to add your own notes." />
                      </label>
                      <textarea
                        value={editForm.nextActions || ''}
                        onChange={(e) => setEditForm({ ...editForm, nextActions: e.target.value })}
                        className="w-full px-3 py-2 border rounded-lg"
                        rows="2"
                      />
                    </div>
                    <div className="flex gap-2 justify-end">
                      <button
                        onClick={() => setEditingId(null)}
                        className="px-4 py-2 border rounded-lg hover:bg-gray-50"
                      >
                        Cancel
                      </button>
                      <button
                        onClick={() => saveEdit(actualIndex)}
                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                      >
                        Save
                      </button>
                    </div>
                  </div>
                ) : (
                  <>
                    <div className="flex items-start justify-between mb-4">
                      <div className="flex-1">
                        <div className="flex items-center gap-3 mb-2">
                          <h3 className="text-xl font-bold text-gray-800">{page.title}</h3>
                          <span className={`px-3 py-1 rounded-full text-xs font-semibold ${
                            page.priority === 'Critical' ? 'bg-red-600 text-white' :
                            page.priority === 'High' ? 'bg-red-100 text-red-800' :
                            page.priority === 'Medium' ? 'bg-yellow-100 text-yellow-800' :
                            page.priority === 'Low' ? 'bg-green-100 text-green-800' :
                            'bg-gray-100 text-gray-800'
                          }`}>
                            {page.priority}
                          </span>
                          {page.ranking && page.ranking.includes('#') && (
                            <span className="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                              {page.ranking}
                            </span>
                          )}
                        </div>
                        <a
                          href={page.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-blue-600 hover:text-blue-800 text-sm flex items-center gap-1 mb-2"
                        >
                          {page.url}
                          <ExternalLink size={14} />
                        </a>
                        <div className="flex gap-4 text-sm text-gray-600">
                          <span>Search Volume: <strong>{formatVolume(page.searchVolume)}</strong></span>
                          {page.focusKeyword && <span>Keyword: <strong>{page.focusKeyword}</strong></span>}
                          {page.ranking && (
                            <span className="flex items-center gap-1">
                              Ranking: <strong className={page.ranking.includes('#') ? 'text-green-600' : 'text-gray-600'}>{page.ranking}</strong>
                            </span>
                          )}
                        </div>
                      </div>
                      <div className="flex gap-2">
                        <button
                          onClick={() => startEdit(actualIndex)}
                          className="px-3 py-1 text-sm text-blue-600 hover:bg-blue-50 rounded-lg border border-blue-300 font-medium"
                        >
                          ✏️ Edit
                        </button>
                        <button
                          onClick={() => exportCsvOne(page.url)}
                          className="px-3 py-1 text-sm text-white bg-green-600 hover:bg-green-700 rounded-lg font-medium"
                          title="Export this page to CSV"
                        >
                          📑 Export CSV
                        </button>
                        {seoMonitorData.xlsxAvailable && (
                          <button
                            onClick={() => exportXlsxOne(page.url)}
                            className="px-3 py-1 text-sm text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg font-medium"
                            title="Export this page to Excel"
                          >
                            📊 Export Excel
                          </button>
                        )}
                        {page.postId && (
                          <a
                            href={`${seoMonitorData.adminUrl}post.php?post=${page.postId}&action=edit`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="px-3 py-1 text-sm text-white bg-indigo-600 hover:bg-indigo-700 hover:text-white focus:text-white rounded-lg font-medium flex items-center gap-1"
                            title="Edit this post in WordPress"
                          >
                            🔗 Edit in WP
                          </a>
                        )}
                        <button
                          onClick={() => deletePage(actualIndex)}
                          className="px-3 py-1 text-sm text-white bg-red-600 hover:bg-red-700 rounded-lg font-medium"
                        >
                          🗑️ Delete
                        </button>
                      </div>
                    </div>

                    <div className="mt-2 flex gap-2">
                      <button
                        onClick={() => setCardTabs({ ...cardTabs, [actualIndex]: 'overview' })}
                        className={`px-2 py-1 text-xs ${perCardTab === 'overview' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500'}`}
                      >
                        Overview
                      </button>
                      <button
                        onClick={() => setCardTabs({ ...cardTabs, [actualIndex]: 'technical' })}
                        className={`px-2 py-1 text-xs ${perCardTab === 'technical' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500'}`}
                      >
                        Technical
                      </button>
                      <button
                        onClick={() => setCardTabs({ ...cardTabs, [actualIndex]: 'performance' })}
                        className={`px-2 py-1 text-xs ${perCardTab === 'performance' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500'}`}
                      >
                        PageSpeed
                      </button>
                      {cardTabs[actualIndex] !== undefined && (
                        <button
                          onClick={() => setCardTabs(prev => { const copy = { ...prev }; delete copy[actualIndex]; return copy; })}
                          className="px-2 py-1 text-xs text-gray-400 hover:text-gray-600"
                          title="Reset to global tabs"
                        >
                          Reset
                        </button>
                      )}
                    </div>

                    {perCardTab === 'overview' && (
                      <div className="space-y-3">
                        {page.rankMathScore && (
                          <div className="bg-blue-50 border-l-4 border-blue-400 p-3 rounded">
                            <span className="font-semibold">RankMath: {page.rankMathScore}/100</span>
                          </div>
                        )}
                        {(page.description || page.focusKeyword) && (
                          <div className="bg-gray-50 border-l-4 border-gray-300 p-3 rounded">
                            <div className="text-sm text-gray-700 space-y-1">
                              {page.description && (
                                <div><span className="font-semibold">Meta Description:</span> {String(page.description).slice(0, 180)}{String(page.description).length > 180 ? '…' : ''}</div>
                              )}
                              {page.focusKeyword && (
                                <div><span className="font-semibold">Focus Keyword:</span> {page.focusKeyword}</div>
                              )}
                            </div>
                          </div>
                        )}
                        {page.onPageActions && (
                          <div className="bg-green-50 border-l-4 border-green-400 p-3 rounded">
                            <div className="flex items-start justify-between">
                              <h4 className="font-semibold text-green-900 mb-2">Actions Completed</h4>
                              {(() => {
                                const acts = parseRecommendations(page.onPageActions);
                                const key = actualIndex;
                                const long = acts.length > 6 || acts.join(' ').length > 250;
                                if (!long) return null;
                                return (
                                  <button
                                    onClick={() => setOpenActions({ ...openActions, [key]: !openActions[key] })}
                                    className="text-sm text-green-700 hover:underline"
                                  >
                                    {openActions[key] ? 'Collapse' : 'Show more'}
                                  </button>
                                );
                              })()}
                            </div>

                            {(() => {
                              const acts = parseRecommendations(page.onPageActions);
                              const key = actualIndex;
                              const long = acts.length > 6 || acts.join(' ').length > 250;
                              const expanded = acts.filter(Boolean).flatMap(expandActionItem).filter(Boolean);
                              return (
                                <div className={`${long && !openActions[key] ? 'max-h-40 overflow-hidden' : ''}`}>
                                  <ul className="list-none pl-0 space-y-1 text-sm text-gray-800">
                                    {expanded.map((act, idx) => (
                                      <li key={idx} className="leading-5">{act}</li>
                                    ))}
                                  </ul>
                                </div>
                              );
                            })()}
                          </div>
                        )}
                        {page.recommendations && (
                          <div className="bg-purple-50 border-l-4 border-purple-400 p-3 rounded">
                            <div className="flex items-start justify-between">
                              <h4 className="font-semibold text-purple-900 mb-2 flex items-center gap-2">💡 SEO Recommendations</h4>
                              {(() => {
                                const recs = parseRecommendations(page.recommendations);
                                const key = actualIndex;
                                const long = recs.length > 6 || recs.join(' ').length > 250;
                                if (!long) return null;
                                return (
                                  <button
                                    onClick={() => setOpenRecommendations({ ...openRecommendations, [key]: !openRecommendations[key] })}
                                    className="text-sm text-purple-700 hover:underline"
                                  >
                                    {openRecommendations[key] ? 'Collapse' : 'Show more'}
                                  </button>
                                );
                              })()}
                            </div>

                            {(() => {
                              const recs = parseRecommendations(page.recommendations);
                              const key = actualIndex;
                              const long = recs.length > 6 || recs.join(' ').length > 250;
                              const rows = [];
                              for (let i = 0; i < recs.length; i += 2) {
                                rows.push([iconize(recs[i]), recs[i + 1] ? iconize(recs[i + 1]) : '']);
                              }
                              return (
                                <div className={`${long && !openRecommendations[key] ? 'max-h-40 overflow-hidden' : ''}`}>
                                  <table className="w-full table-fixed text-sm text-gray-700 border-separate" style={{ borderSpacing: '0 6px' }}>
                                    <tbody>
                                      {rows.map((pair, r) => (
                                        <tr key={r} className="align-top bg-white rounded-md shadow-sm">
                                          <td className="w-1/2 pr-4 py-3 align-top border-r border-gray-100 whitespace-pre-wrap">{pair[0]}</td>
                                          <td className="w-1/2 pl-4 py-3 align-top text-gray-700 whitespace-pre-wrap">{pair[1]}</td>
                                        </tr>
                                      ))}
                                    </tbody>
                                  </table>
                                </div>
                              );
                            })()}

                            <p className="text-xs text-purple-600 mt-2">✨ Personalized suggestions to improve this page's search ranking</p>
                          </div>
                        )}
                        {page.nextActions && (
                          <div className="bg-yellow-50 border-l-4 border-yellow-400 p-3 rounded">
                            <h4 className="font-semibold text-yellow-900 mb-2">Next Actions</h4>
                            {(() => {
                              const source = Array.isArray(page.nextActions) ? page.nextActions.join('\n') : page.nextActions;
                              const items = parseRecommendations(source);
                              return (
                                <ul className="list-none pl-0 space-y-1 text-sm text-gray-800">
                                  {items.filter(Boolean).map((nxt, i) => (
                                    <li key={i} className="leading-5">{iconize(nxt)}</li>
                                  ))}
                                </ul>
                              );
                            })()}
                          </div>
                        )}
                      </div>
                    )}

                    {perCardTab === 'technical' && (
                      <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                          <div className="flex justify-between p-2 bg-gray-50 rounded">
                            <span className="text-sm font-medium">Internal Links</span>
                            <span className="font-semibold">{page.internalLinks || '-'}</span>
                          </div>
                          <div className="flex justify-between p-2 bg-gray-50 rounded">
                            <span className="text-sm font-medium">External Links</span>
                            <span className={`font-semibold ${page.externalLinks === '0' ? 'text-red-600' : ''}`}>
                              {page.externalLinks || '-'}
                            </span>
                          </div>
                        </div>
                        <div className="space-y-2">
                          <div className="flex justify-between p-2 bg-gray-50 rounded">
                            <span className="text-sm font-medium">Alt Images</span>
                            <span className={page.altImages && page.altImages.includes('Missing') ? 'text-red-600' : 'text-green-600'}>
                              {page.altImages || '-'}
                            </span>
                          </div>
                        </div>
                      </div>
                    )}

                    {perCardTab === 'performance' && (
                      <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                          <div className="bg-purple-50 rounded-lg p-4 border border-purple-200">
                            <div className="flex justify-between mb-2">
                              <span className="text-sm font-medium">Mobile</span>
                              <Zap size={20} />
                            </div>
                            {page.pageSpeedMobile ? (
                              <div className="text-3xl font-bold text-purple-900">{page.pageSpeedMobile}</div>
                            ) : (
                              <div className="text-gray-500 text-sm">Not tested</div>
                            )}
                          </div>
                          <div className="bg-blue-50 rounded-lg p-4 border border-blue-200">
                            <div className="flex justify-between mb-2">
                              <span className="text-sm font-medium">Desktop</span>
                              <Zap size={20} />
                            </div>
                            {page.pageSpeedDesktop ? (
                              <div className="text-3xl font-bold text-blue-900">{page.pageSpeedDesktop}</div>
                            ) : (
                              <div className="text-gray-500 text-sm">Not tested</div>
                            )}
                          </div>
                        </div>
                        
                        <div className="mt-4">
                          <div className="flex gap-2">
                            <button
                              onClick={() => runPageSpeedTest(actualIndex)}
                              disabled={testingPageSpeed === actualIndex}
                              className="inline-flex items-center gap-1.5 bg-purple-600 text-white px-3 py-2 rounded-md hover:bg-purple-800 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
                            >
                              <Zap size={14} />
                              {testingPageSpeed === actualIndex ? 'Testing...' : 'Auto Test & Save'}
                            </button>
                            <a
                              href={`https://pagespeed.web.dev/analysis?url=${encodeURIComponent(page.url)}`}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="inline-flex items-center gap-1.5 bg-green-600 text-white px-3 py-2 rounded-md hover:bg-green-800 hover:text-white text-sm font-medium focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                            >
                              <ExternalLink size={14} />
                              View in Google
                            </a>
                          </div>
                          {testingPageSpeed === actualIndex && (
                            <div className="mt-2 text-center text-sm text-gray-600 animate-pulse">
                              Testing PageSpeed... This may take 30-60 seconds
                            </div>
                          )}
                          {(page.pageSpeedMobileUrl || page.pageSpeedDesktopUrl) && (
                            <div className="mt-3 flex gap-2 text-sm justify-center">
                              {page.pageSpeedMobileUrl && (
                                <a
                                  href={page.pageSpeedMobileUrl}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="text-purple-600 hover:text-purple-800 flex items-center gap-1 font-medium"
                                >
                                  📱 Mobile Report <ExternalLink size={12} />
                                </a>
                              )}
                              {page.pageSpeedDesktopUrl && (
                                <a
                                  href={page.pageSpeedDesktopUrl}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="text-blue-600 hover:text-blue-800 flex items-center gap-1 font-medium"
                                >
                                  💻 Desktop Report <ExternalLink size={12} />
                                </a>
                              )}
                            </div>
                          )}
                        </div>
                        
                        {!page.pageSpeedMobile && (
                          <div className="bg-blue-50 border-l-4 border-blue-400 p-4 rounded mt-4">
                            <h4 className="font-semibold text-blue-900 mb-2">💡 PageSpeed Testing Options</h4>
                            <ul className="text-sm text-gray-700 space-y-1 list-disc list-inside">
                              <li><strong>Auto Test & Save:</strong> Automatically runs PageSpeed test and saves scores to your dashboard</li>
                              <li><strong>View in Google:</strong> Opens the official Google PageSpeed Insights website to view detailed analysis. You can manually add the scores via the Edit button if Google API is not working or not configured.</li>
                            </ul>
                            <p className="text-xs text-gray-600 mt-2">
                              💡 <strong>Tip:</strong> Configure your Google API key in Settings for automated testing with 25,000 free tests/day
                            </p>
                          </div>
                        )}
                      </div>
                    )}

                    <div className="mt-4 pt-4 border-t space-y-2">
                      <h4 className="text-sm font-semibold text-gray-700">⚡ Quick Edit</h4>
                      <div className="flex gap-2">
                        <input
                          type="text"
                          placeholder="Update search volume (e.g., 5000)"
                          onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                              updatePage(actualIndex, 'searchVolume', e.target.value);
                              e.target.value = '';
                            }
                          }}
                          className="flex-1 px-3 py-1 border rounded text-sm"
                        />
                        <input
                          type="text"
                          placeholder="Update ranking (e.g., #25)"
                          onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                              updatePage(actualIndex, 'ranking', e.target.value);
                              e.target.value = '';
                            }
                          }}
                          className="flex-1 px-3 py-1 border rounded text-sm"
                        />
                        <select
                          value={page.priority}
                          onChange={(e) => updatePage(actualIndex, 'priority', e.target.value)}
                          className="px-3 py-1 border rounded text-sm"
                        >
                          <option>Low</option>
                          <option>Medium</option>
                          <option>High</option>
                          <option>Critical</option>
                        </select>
                      </div>
                    </div>
                  </>
                )}
              </div>
            );
          })}
        </div>

        {filteredPages.length === 0 && (
          <div className="bg-white rounded-lg shadow-md p-12 text-center">
            <AlertCircle size={48} />
            <p className="text-gray-600">No pages match your filters</p>
          </div>
        )}
      </div>
    </div>
  );
};

// Initialize React app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  const rootElement = document.getElementById('seo-monitor-root');
  if (rootElement) {
    const root = createRoot(rootElement);
    root.render(<SEOMonitor />);
  }
});
