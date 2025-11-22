# SEO Page Monitor & Optimizer

A WordPress plugin for tracking and monitoring SEO rankings, PageSpeed scores, and optimization tasks for your pages.

## Features

- **Page Monitoring**: Track multiple pages with their SEO metrics
- **Ranking Tracking**: Monitor search engine rankings for focus keywords
- **PageSpeed Integration**: Quick access to PageSpeed Insights
- **Priority Management**: Organize pages by priority (Critical, High, Medium, Low)
- **Technical SEO**: Track internal/external links, alt images, and more
- **Action Tracking**: Document completed actions and plan next steps
- **Search & Filter**: Easily find and filter pages by priority or search terms
- **Statistics Dashboard**: Overview of total pages, rankings, and tasks

## Installation

1. Upload the `seo-page-monitor` folder to `/wp-content/plugins/`
2. Navigate to the plugin directory: `cd wp-content/plugins/seo-page-monitor`
3. Install dependencies: `npm install`
4. Build the React app: `npm run build`
5. Activate the plugin through the 'Plugins' menu in WordPress
6. Access the plugin via the 'SEO Monitor' menu item in the WordPress admin

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

## Usage

1. Navigate to **SEO Monitor** in the WordPress admin menu
2. View all tracked pages with their current metrics
3. Click the edit icon to update page details
4. Use the tabs to switch between Overview, Technical, and PageSpeed views
5. Filter pages by priority using the dropdown
6. Search for specific pages using the search bar
7. Quick-update rankings by typing in the ranking field at the bottom of each page card

## Data Storage

All page data is stored in WordPress options table using the `seo_monitor_pages` option key. Data persists across sessions and is automatically saved when updated.

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Modern browser with JavaScript enabled

## Technologies

- **Frontend**: React 18, Tailwind CSS
- **Backend**: WordPress REST API
- **Build Tool**: Webpack, Babel

## Support

For support, please visit [CMobile](https://www.cmobile.com.au)

## License

GPL v2 or later

## Changelog

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
