#!/usr/bin/env node

const fs = require('fs-extra');
const path = require('path');
const yargs = require('yargs/yargs');
const { hideBin } = require('yargs/helpers');

// Configuration
const CONFIG = {
  sourceDir: path.resolve(__dirname, '..'),
  targetDir: '/Users/chadmacbook/Local Sites/handy-crab/app/public/wp-content/plugins/handy-custom',
  excludePatterns: [
    'node_modules',
    'tests',
    'scripts',
    '.git',
    '.gitignore',
    'package.json',
    'package-lock.json',
    'playwright.config.js',
    '*.log',
    '.DS_Store'
  ]
};

/**
 * Deploy plugin to Local by WP Engine site
 */
async function deployToLocal(options = {}) {
  const { verbose = false, dryRun = false } = options;
  
  try {
    console.log('🚀 Starting deployment to Local by WP Engine...');
    
    if (verbose) {
      console.log(`Source: ${CONFIG.sourceDir}`);
      console.log(`Target: ${CONFIG.targetDir}`);
    }
    
    // Check if target directory exists
    if (!await fs.pathExists(CONFIG.targetDir)) {
      console.log(`📁 Creating target directory: ${CONFIG.targetDir}`);
      if (!dryRun) {
        await fs.ensureDir(CONFIG.targetDir);
      }
    }
    
    // Get list of files to copy
    const filesToCopy = await getFilesToCopy();
    
    if (verbose) {
      console.log(`\n📋 Files to copy (${filesToCopy.length}):`);
      filesToCopy.forEach(file => console.log(`  - ${file}`));
    }
    
    if (dryRun) {
      console.log(`\n🔍 DRY RUN: Would copy ${filesToCopy.length} files`);
      return;
    }
    
    // Copy files
    let copiedCount = 0;
    for (const file of filesToCopy) {
      const sourcePath = path.join(CONFIG.sourceDir, file);
      const targetPath = path.join(CONFIG.targetDir, file);
      
      // Ensure target directory exists
      await fs.ensureDir(path.dirname(targetPath));
      
      // Copy file
      await fs.copy(sourcePath, targetPath, { overwrite: true });
      copiedCount++;
      
      if (verbose) {
        console.log(`  ✅ ${file}`);
      }
    }
    
    console.log(`\n✅ Deployment completed! Copied ${copiedCount} files.`);
    console.log(`🌐 Local site: http://localhost:10008`);
    
  } catch (error) {
    console.error('❌ Deployment failed:', error.message);
    process.exit(1);
  }
}

/**
 * Get list of files to copy (excluding patterns)
 */
async function getFilesToCopy() {
  const files = [];
  
  async function scanDirectory(dir, baseDir = '') {
    const items = await fs.readdir(path.join(CONFIG.sourceDir, dir));
    
    for (const item of items) {
      const relativePath = path.join(baseDir, item);
      const fullPath = path.join(CONFIG.sourceDir, dir, item);
      
      // Check if item should be excluded
      if (shouldExclude(relativePath)) {
        continue;
      }
      
      const stat = await fs.stat(fullPath);
      
      if (stat.isDirectory()) {
        await scanDirectory(path.join(dir, item), relativePath);
      } else {
        files.push(path.join(dir, item).replace(/\\/g, '/'));
      }
    }
  }
  
  await scanDirectory('.');
  return files;
}

/**
 * Check if file/directory should be excluded
 */
function shouldExclude(relativePath) {
  return CONFIG.excludePatterns.some(pattern => {
    if (pattern.includes('*')) {
      // Simple glob pattern matching
      const regex = new RegExp(pattern.replace(/\*/g, '.*'));
      return regex.test(relativePath);
    } else {
      // Exact match or directory match
      return relativePath === pattern || relativePath.startsWith(pattern + '/');
    }
  });
}

/**
 * Get current plugin version
 */
async function getCurrentVersion() {
  try {
    const mainPluginFile = path.join(CONFIG.sourceDir, 'handy-custom.php');
    const content = await fs.readFile(mainPluginFile, 'utf8');
    
    const versionMatch = content.match(/Version:\s*(.+)/);
    return versionMatch ? versionMatch[1].trim() : 'unknown';
  } catch (error) {
    return 'unknown';
  }
}

// CLI setup
const argv = yargs(hideBin(process.argv))
  .command('$0', 'Deploy plugin to Local by WP Engine site', (yargs) => {
    return yargs
      .option('verbose', {
        alias: 'v',
        type: 'boolean',
        description: 'Show verbose output'
      })
      .option('dry-run', {
        alias: 'd',
        type: 'boolean',
        description: 'Show what would be copied without actually copying'
      });
  })
  .help()
  .argv;

// Run deployment if called directly
if (require.main === module) {
  deployToLocal(argv);
}

module.exports = { deployToLocal, getCurrentVersion };