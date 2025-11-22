Google Sheets integration
=========================

How to set up
--------------

1. Create a Google Cloud Service Account with access to the Sheets API.
2. Download the JSON credentials for the service account.
3. Share your Google Spreadsheet with the service account email (Editor permissions recommended).
4. In the plugin Settings -> SEO Monitor -> PageSpeed API Configuration, paste your Spreadsheet ID and Service Account JSON.

How to get the Spreadsheet ID
-----------------------------

- Open your Google Spreadsheet in the browser. Look at the URL; the ID is the part between <code>/d/</code> and <code>/edit</code>. Example URL: <code>https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit#gid=0</code> so the ID is <code>1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms</code>.

Sharing the sheet with your Service Account
------------------------------------------

- When you create the Service Account in Google Cloud, you will have an email address like <code>my-sa@project-id.iam.gserviceaccount.com</code>.
- Open your spreadsheet, click the <strong>Share</strong> button, and add the Service Account email with <strong>Editor</strong> permissions.

Base64 and constants
---------------------

- For safety and to avoid string-escaping issues when storing JSON in the DB, encode the JSON with base64 before pasting into the plugin settings, or set the `SEO_MONITOR_GOOGLE_SA_JSON` constant with the base64 value.
- Alternatively, set the constant `SEO_MONITOR_GOOGLE_SA_FILE` with the path to the service account JSON file on your server (the plugin checks this if the settings area is empty).

Notes
-----
- The plugin stores the Service Account JSON base64-encoded in the database. You can provide it as a constant instead with `SEO_MONITOR_GOOGLE_SA_JSON`.
- For advanced setups, place the JSON file on the server and set `SEO_MONITOR_GOOGLE_SA_FILE` to the file path.
- By default, the plugin maps row values by order: Timestamp, URL, SEO Score, Status, Notes. Enable "Read headers" to map by header labels dynamically.

Troubleshooting
---------------
- Ensure the spreadsheet is shared with the Service Account email.
- If you see rate limit errors from Google, enable batching or use the CLI sync to run in controlled batches.
