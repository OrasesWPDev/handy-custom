#!/usr/bin/env node

const chokidar = require('chokidar');
const path = require('path');
const { deployToLocal } = require('./deploy-to-local');

// Configuration
const CONFIG = {
  watchPaths: [
    'handy-custom.php',
    'includes/**/*.php',
    'templates/**/*.php',
    'assets/**/*.css',
    'assets/**/*.js',
    'CLAUDE.md'
  ],
  ignorePaths: [
    'node_modules/**',
    'tests/**',
    'scripts/**',
    '.git/**',
    '**/*.log',
    '**/.DS_Store'
  ],
  debounceDelay: 1000 // ms
};

let deployTimeout;
let isDeploying = false;

/**
 * Debounced deployment function
 */
function debouncedDeploy() {
  if (deployTimeout) {
    clearTimeout(deployTimeout);
  }
  
  deployTimeout = setTimeout(async () => {
    if (isDeploying) {
      console.log('⏳ Deployment already in progress, skipping...');
      return;
    }
    
    isDeploying = true;
    console.log('🔄 Files changed, deploying...');
    
    try {
      await deployToLocal({ verbose: false });
      console.log('✅ Auto-deployment completed');
    } catch (error) {
      console.error('❌ Auto-deployment failed:', error.message);
    } finally {
      isDeploying = false;
    }
  }, CONFIG.debounceDelay);
}

/**
 * Start file watcher
 */
function startWatcher() {
  console.log('👀 Starting file watcher for automatic deployment...');
  console.log('📁 Watching paths:', CONFIG.watchPaths);
  console.log('🚫 Ignoring paths:', CONFIG.ignorePaths);
  console.log('🌐 Target: http://localhost:10008\n');
  
  const watcher = chokidar.watch(CONFIG.watchPaths, {
    ignored: CONFIG.ignorePaths,
    persistent: true,
    ignoreInitial: true,
    awaitWriteFinish: {
      stabilityThreshold: 200,
      pollInterval: 100
    }
  });
  
  watcher
    .on('change', (filePath) => {
      console.log(`📝 Changed: ${path.relative(process.cwd(), filePath)}`);
      debouncedDeploy();
    })
    .on('add', (filePath) => {
      console.log(`➕ Added: ${path.relative(process.cwd(), filePath)}`);
      debouncedDeploy();
    })
    .on('unlink', (filePath) => {
      console.log(`🗑️  Removed: ${path.relative(process.cwd(), filePath)}`);
      debouncedDeploy();
    })
    .on('error', (error) => {
      console.error('❌ Watcher error:', error);
    });
  
  // Initial deployment
  console.log('🚀 Performing initial deployment...');
  deployToLocal({ verbose: false })
    .then(() => {
      console.log('✅ Initial deployment completed');
      console.log('👀 Now watching for changes... (Press Ctrl+C to stop)\n');
    })
    .catch(error => {
      console.error('❌ Initial deployment failed:', error.message);
    });
  
  // Graceful shutdown
  process.on('SIGINT', () => {
    console.log('\n🛑 Stopping file watcher...');
    watcher.close();
    process.exit(0);
  });
  
  process.on('SIGTERM', () => {
    console.log('\n🛑 Stopping file watcher...');
    watcher.close();
    process.exit(0);
  });
}

// Start watcher if called directly
if (require.main === module) {
  startWatcher();
}

module.exports = { startWatcher };