# SEO Page Monitor & Optimizer - Installation Guide

## Quick Start

Your WordPress plugin has been created! Here's how to use it:

## Plugin Location

The plugin has been created at:
```
wp-content/plugins/seo-page-monitor/
```

## Files Created

```
seo-page-monitor/
├── seo-page-monitor.php       (Main plugin file)
├── package.json                (Node dependencies)
├── webpack.config.js           (Build configuration)
├── README.md                   (Documentation)
├── .gitignore                  (Git ignore rules)
└── assets/
    ├── js/
    │   ├── app.jsx            (React source code)
    │   └── app.js             (Built JavaScript - ready to use)
    └── css/
        └── style.css          (Plugin styles)
```

## Activation Steps

### ✅ Already Done:
1. ✅ Dependencies installed (`npm install`)
2. ✅ React app built (`npm run build`)
3. ✅ Plugin files created and ready

### Next Step - Activate in WordPress:

1. **Go to WordPress Admin**
   - Navigate to: http://your-site.local/wp-admin/plugins.php

2. **Find "SEO Page Monitor & Optimizer"** in the plugin list

3. **Click "Activate"**

4. **Access the Plugin**
   - Look for "SEO Monitor" in the left admin menu (with chart icon)
   - Click it to open the dashboard

## Features

✨ **Dashboard Features:**
- Track multiple pages with SEO metrics
- Monitor search rankings
- PageSpeed score tracking
- Priority management (Critical/High/Medium/Low)
- Search and filter pages
- Quick edit functionality
- Technical SEO tracking (links, images)
- Action planning and tracking

## How to Use

1. **View Pages**: All tracked pages are displayed on the main dashboard
2. **Edit Page**: Click the edit (pencil) icon on any page card
3. **Update Ranking**: Type a ranking (e.g., "#25") in the quick-update field
4. **Change Priority**: Use the dropdown at the bottom of each card
5. **Filter**: Use the priority dropdown to filter pages
6. **Search**: Use the search box to find specific pages
7. **Tabs**: Switch between Overview, Technical, and PageSpeed views
8. **PageSpeed Test**: Click "Run Test" button to check page speed

## Data Storage

- All page data is stored in WordPress database
- Uses option name: `seo_monitor_pages`
- Data persists across sessions
- Automatically saves on updates

## Default Data

The plugin comes pre-loaded with sample CMobile NBN pages for demonstration. You can:
- Edit these pages
- Add new pages (edit the data in the React component)
- Delete unwanted pages

## Development

If you need to make changes to the React code:

```bash
# Navigate to plugin directory
cd wp-content/plugins/seo-page-monitor

# Start development mode (auto-rebuild on changes)
npm run dev

# Build for production
npm run build
```

## Troubleshooting

### Plugin Not Showing
- Ensure the plugin is activated
- Check for JavaScript errors in browser console
- Verify `app.js` file exists in `assets/js/`

Google API: Installing PHP Client
---------------------------------

This plugin optionally uses the official Google PHP Client (google/apiclient) for the 
Google Sheets integration. To enable it, run the following from the plugin directory:

```bash
composer require google/apiclient:^2.12
```

If you can't run composer in the hosting environment, you may also vendor the
`vendor` directory manually from a local build.

### Data Not Saving
- Check REST API is working: Visit `/wp-json/seo-monitor/v1/pages`
- Verify user has `manage_options` capability
- Check browser console for errors

### Styling Issues
- Clear browser cache
- Check Tailwind CSS is loading from CDN
- Verify `style.css` is enqueued

## Requirements

- ✅ WordPress 5.0+
- ✅ PHP 7.2+
- ✅ Node.js 14+ (for development only)
- ✅ Modern browser with JavaScript enabled

## Support

For issues or questions:
- Check README.md for detailed documentation
- Review browser console for JavaScript errors
- Check WordPress debug log for PHP errors

## Next Steps

1. **Activate the plugin** in WordPress admin
2. **Open "SEO Monitor"** from the admin menu
3. **Start tracking your pages!**

Enjoy tracking your SEO! 🚀
