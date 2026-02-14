#!/usr/bin/env node
/**
 * SRI Hash Generator for CDN Scripts
 *
 * This script generates SHA-384 integrity hashes for CDN scripts used in the application.
 * Run this script whenever you update CDN script versions to get new SRI hashes.
 *
 * Usage: node generate-sri-hashes.js
 *
 * Requirements: Node.js with HTTPS and crypto modules (built-in)
 */

const https = require('https');
const crypto = require('crypto');

// CDN URLs used in the application
const cdnScripts = [
  {
    name: 'Chart.js',
    url: 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js',
    usedIn: ['session.php', 'admin/results.php']
  },
  {
    name: 'html2canvas',
    url: 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
    usedIn: ['admin/results.php']
  },
  {
    name: 'jsPDF',
    url: 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
    usedIn: ['admin/results.php']
  }
];

/**
 * Generate SRI hash for a given URL
 */
function generateSRI(script) {
  return new Promise((resolve, reject) => {
    console.log(`\nFetching ${script.name}...`);
    console.log(`URL: ${script.url}`);

    https.get(script.url, { headers: { 'User-Agent': 'SRI-Hash-Generator' } }, (res) => {
      if (res.statusCode !== 200) {
        reject(new Error(`HTTP ${res.statusCode} for ${script.url}`));
        return;
      }

      const chunks = [];

      res.on('data', (chunk) => chunks.push(chunk));

      res.on('end', () => {
        const buffer = Buffer.concat(chunks);
        const sha384 = crypto.createHash('sha384').update(buffer).digest('base64');
        const sha512 = crypto.createHash('sha512').update(buffer).digest('base64');

        resolve({
          ...script,
          sha384: `sha384-${sha384}`,
          sha512: `sha512-${sha512}`,
          size: buffer.length
        });
      });

    }).on('error', reject);
  });
}

/**
 * Main execution
 */
async function main() {
  console.log('===============================================');
  console.log('SRI Hash Generator for Feedbackspinne CDN Scripts');
  console.log('===============================================');

  try {
    const results = [];

    for (const script of cdnScripts) {
      const result = await generateSRI(script);
      results.push(result);
    }

    console.log('\n\n===============================================');
    console.log('GENERATED SRI HASHES');
    console.log('===============================================\n');

    results.forEach(r => {
      console.log(`${r.name} (${(r.size / 1024).toFixed(2)} KB)`);
      console.log(`Files: ${r.usedIn.join(', ')}`);
      console.log(`URL: ${r.url}`);
      console.log(`SHA-384: ${r.sha384}`);
      console.log(`SHA-512: ${r.sha512}`);
      console.log('');
      console.log('HTML snippet:');
      console.log(`<script src="${r.url}"`);
      console.log(`        integrity="${r.sha384}"`);
      console.log(`        crossorigin="anonymous"></script>`);
      console.log('\n---\n');
    });

    console.log('===============================================');
    console.log('NEXT STEPS:');
    console.log('===============================================');
    console.log('1. Copy the SHA-384 hashes above');
    console.log('2. Update the integrity attributes in:');
    results.forEach(r => {
      r.usedIn.forEach(file => console.log(`   - ${file}`));
    });
    console.log('3. Test the application to ensure scripts load correctly');
    console.log('4. Commit the changes\n');

  } catch (error) {
    console.error('\n❌ Error:', error.message);
    console.error('\nThis may be due to:');
    console.error('  - Network connectivity issues');
    console.error('  - CDN unavailability');
    console.error('  - Firewall restrictions\n');
    process.exit(1);
  }
}

// Run if executed directly
if (require.main === module) {
  main();
}

module.exports = { generateSRI, cdnScripts };
