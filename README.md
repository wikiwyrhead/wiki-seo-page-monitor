<div align="center">
  <h1>📊 SEO Page Monitor & Optimizer</h1>
  <p>A powerful WordPress plugin for comprehensive SEO monitoring, performance tracking, and optimization workflow management.</p>
  
  [![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/seo-page-monitor?style=flat-square)](https://wordpress.org/plugins/seo-page-monitor/)
  [![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg?style=flat-square)](http://www.gnu.org/licenses/gpl-2.0.html)
  [![WordPress Tested](https://img.shields.io/wordpress/v/seo-page-monitor?label=WordPress&style=flat-square)](https://wordpress.org/plugins/seo-page-monitor/)
  [![PHP Version](https://img.shields.io/badge/PHP-7.2%2B-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://php.net/)
  
  [![Screenshot](https://via.placeholder.com/800x400/2d3748/ffffff?text=SEO+Page+Monitor+Dashboard)](screenshot-1.png)
</div>

## 🚀 Features

### 📈 SEO & Performance Tracking

- **Comprehensive Page Monitoring**: Track unlimited pages with detailed SEO metrics
- **Ranking Tracker**: Monitor search engine positions for focus keywords
- **PageSpeed Insights**: Integrated performance scoring and optimization suggestions
- **Mobile Usability**: Track mobile-friendliness and Core Web Vitals

### 🔍 Technical SEO Tools

- **Link Analysis**: Monitor internal and external link profiles
- **Image Optimization**: Track missing alt texts and large images
- **Header Structure**: Analyze and optimize heading hierarchy
- **Meta Information**: Monitor meta titles and descriptions

### 🛠️ Workflow Management

- **Priority System**: Categorize pages by priority (Critical, High, Medium, Low)
- **Action Tracking**: Document completed actions and plan next steps
- **Team Collaboration**: Notes and change history for team coordination
- **Custom Labels**: Tag and categorize pages for better organization

### 📊 Reporting & Analytics

- **Interactive Dashboard**: Visual overview of your SEO health
- **Export Capabilities**: Export data to Excel/CSV for further analysis
- **Historical Data**: Track changes and improvements over time
- **Custom Metrics**: Define and monitor your own KPIs

## 🛠 Installation

### Prerequisites
- WordPress 5.0 or higher
- PHP 7.2 or higher
- Node.js 14.x or higher (for development)
- MySQL 5.6 or higher

### Quick Installation
1. Download the latest release from the [WordPress Plugin Directory](https://wordpress.org/plugins/seo-page-monitor/)
2. In your WordPress admin, go to **Plugins > Add New > Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Activate the plugin
5. Access the plugin via **SEO Monitor** in the WordPress admin menu

### Manual Installation
```bash
# 1. Upload the plugin
cd /path/to/wordpress/wp-content/plugins/
unzip seo-page-monitor.zip

# 2. Install dependencies and build assets (for development)
cd seo-page-monitor
npm install
npm run build
```

### WordPress CLI
```bash
wp plugin install seo-page-monitor --activate
```

## Development

### Prerequisites
- Node.js (v14 or higher)
- npm or yarn

### Build Commands

```bash
# Install dependencies
npm install

# Build for production
npm run build

# Development mode with watch
npm run dev
```

## 🚀 Getting Started

### Dashboard Overview

1. Navigate to **SEO Monitor** in your WordPress admin
2. The main dashboard displays an overview of all tracked pages
3. Each card shows key metrics at a glance
4. Use the color-coded priority indicators to identify critical pages

### Adding a New Page

1. Click **Add New Page** in the top-right corner
2. Enter the page URL and focus keyword
3. Set the priority level (Critical, High, Medium, Low)
4. Add any initial notes or actions
5. Click **Save** to start tracking

### Managing Pages

- **Edit**: Click the pencil icon to update page details
- **Quick Edit**: Hover over any metric to make quick updates
- **Bulk Actions**: Select multiple pages for batch operations
- **Filtering**: Use the priority dropdown and search bar to find specific pages

### Understanding Metrics

- **SEO Score**: Overall SEO health (0-100)
- **Performance**: PageSpeed performance score
- **Accessibility**: WCAG compliance score
- **Best Practices**: Adherence to web best practices
- **SEO**: Technical SEO health check

## 🛠 Advanced Configuration

### Data Storage & Security

- All data is stored in the WordPress database using custom tables for optimal performance
- Sensitive data is encrypted at rest
- Regular database optimization is performed automatically
- Data is backed up with your regular WordPress backups

### API Integration
```php
// Example: Access plugin data programmatically
$seo_monitor_data = get_option('seo_monitor_pages');

// Add custom data to the monitoring system
do_action('seo_monitor_track_page', [
    'url' => 'https://example.com/page',
    'priority' => 'high',
    'meta' => [
        'custom_metric' => 'value'
    ]
]);
```

### Hooks & Filters
- `seo_monitor_before_save_page` - Modify page data before saving
- `seo_monitor_after_save_page` - Perform actions after saving a page
- `seo_monitor_metrics` - Add custom metrics to the dashboard
- `seo_monitor_export_columns` - Customize exported data columns

## 🔍 Troubleshooting

### Common Issues

1. **Plugin not activating**
   - Verify PHP version meets minimum requirements
   - Check for plugin conflicts
   - Review WordPress debug log for errors

2. **Missing data**
   - Ensure the WordPress cron is running
   - Check API key permissions (if using external services)
   - Verify user capabilities

3. **Performance issues**
   - Increase PHP memory limit
   - Optimize database tables
   - Consider using a caching plugin

### Getting Help
- Check the [FAQ](#) section
- Review the [documentation](#)
- [Open an issue](https://github.com/your-repo/seo-page-monitor/issues)
- Email support: support@example.com

## 🤝 Support & Community

We're here to help! Here's how you can get support:

### Documentation

- [User Guide](#)
- [Developer Documentation](#)
- [API Reference](#)

### Support Channels

- [Community Forum](#)
- [Email Support](mailto:support@example.com)
- [Live Chat](https://www.cmobile.com.au/chat)
- [Help Center](https://help.cmobile.com.au)

### Professional Services

- [Custom Development](#)
- [SEO Audit](#)
- [Performance Optimization](#)

## 📄 License

SEO Page Monitor is licensed under the [GNU General Public License v2 or later](http://www.gnu.org/licenses/gpl-2.0.html).

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## 👥 Contributing

We welcome contributions! Here's how you can help:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Setup

```bash
# Clone the repository
git clone https://github.com/your-username/seo-page-monitor.git
cd seo-page-monitor

# Install dependencies
npm install

# Start development server
npm run dev

# Build for production
npm run build
```

### Testing

```bash
# Run unit tests
npm test

# Run E2E tests
npm run test:e2e
```

## 📋 Changelog

### 1.2.1
- Fix (REST): Add no-cache headers to all `seo-monitor/v1` endpoints to prevent LiteSpeed/proxy/browser caching from hiding newly-saved pages.
- Build: Packaging scripts updated to exclude dev/test/CI artifacts (keep `vendor/` for installable zip).
- Frontend: Rebuilt production assets via `webpack --mode production`.
- Docs: Enhanced README and changelog.

### 1.2.0
- UI: Actions Completed and Next Actions render as iconized bullets with per-line wrapping and Show more/Collapse.
- UI: SEO Recommendations restored to a neat two-column table for readability.
- Export (Excel): Overview columns I/J/K (Actions/Recommendations/Next) now export one item per line with emoji icons and wrapping.
- Export (Excel): Improved splitter for emojis/labels and special handling for Header Structure lines; normalized newlines; auto row height.
- Persistence: Preserve newlines for Actions Completed so UI/exports keep intended line breaks.

### 1.1.0
- Export: Add Export CSV action (Excel-compatible) from Settings
- Security: CSV sanitization to prevent Excel formula injection
- Version: Bump plugin header to 1.1.0

### 1.0.1
- Admin: Google Sheets readiness notices on Settings
- Sheets: Header-aware writes (align to header order when enabled)
- Sheets: Optional hard row deletion (fallback to clear if sheetId unavailable)
- Security: Safer defaults for fetch (sslverify/reject_unsafe_urls) with DEV override
- Packaging: Lean zips via scripts and .gitattributes; GitHub Actions release workflow

### 1.0.0
- Initial release
- Page monitoring and tracking
- REST API integration
- Priority management
- PageSpeed integration
