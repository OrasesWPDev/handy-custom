#!/usr/bin/env node

const fs = require('fs-extra');
const path = require('path');
const yargs = require('yargs');

// Configuration - paths to files that need version updates
const VERSION_FILES = [
  {
    file: 'handy-custom.php',
    patterns: [
      {
        regex: /(\* Version:\s*)(.+)/,
        replacement: '$1{VERSION}'
      },
      {
        regex: /(define\('HANDY_CUSTOM_VERSION',\s*')([^']+)(')/,
        replacement: '$1{VERSION}$3'
      }
    ]
  },
  {
    file: 'includes/class-handy-custom.php',
    patterns: [
      {
        regex: /(const VERSION\s*=\s*')([^']+)(')/,
        replacement: '$1{VERSION}$3'
      }
    ]
  },
  {
    file: 'package.json',
    patterns: [
      {
        regex: /("version":\s*")([^"]+)(")/,
        replacement: '$1{VERSION}$3'
      }
    ]
  }
];

/**
 * Get current version from main plugin file
 */
async function getCurrentVersion() {
  try {
    const mainFile = path.resolve('handy-custom.php');
    const content = await fs.readFile(mainFile, 'utf8');
    
    const versionMatch = content.match(/\* Version:\s*(.+)/);
    return versionMatch ? versionMatch[1].trim() : null;
  } catch (error) {
    throw new Error(`Failed to read current version: ${error.message}`);
  }
}

/**
 * Validate version format (semantic versioning)
 */
function validateVersion(version) {
  const semverRegex = /^(\d+)\.(\d+)\.(\d+)(?:-([a-zA-Z0-9.-]+))?(?:\+([a-zA-Z0-9.-]+))?$/;
  return semverRegex.test(version);
}

/**
 * Update version in all relevant files
 */
async function updateVersion(newVersion, options = {}) {
  const { dryRun = false, verbose = false } = options;
  
  try {
    // Validate new version format
    if (!validateVersion(newVersion)) {
      throw new Error(`Invalid version format: ${newVersion}. Please use semantic versioning (e.g., 2.0.4)`);
    }
    
    const currentVersion = await getCurrentVersion();
    
    if (verbose) {
      console.log(`Current version: ${currentVersion || 'unknown'}`);
      console.log(`New version: ${newVersion}`);
    }
    
    if (currentVersion === newVersion) {
      console.log(`⚠️  Version is already ${newVersion}`);
      return;
    }
    
    console.log(`🔄 Updating plugin version from ${currentVersion} to ${newVersion}...`);
    
    const updatedFiles = [];
    
    // Process each file
    for (const fileConfig of VERSION_FILES) {
      const filePath = path.resolve(fileConfig.file);
      
      // Check if file exists
      if (!await fs.pathExists(filePath)) {
        if (verbose) {
          console.log(`⚠️  File does not exist: ${fileConfig.file}`);
        }
        continue;
      }
      
      // Read file content
      let content = await fs.readFile(filePath, 'utf8');
      let fileModified = false;
      
      // Apply each pattern replacement
      for (const pattern of fileConfig.patterns) {
        const originalContent = content;
        const replacement = pattern.replacement.replace('{VERSION}', newVersion);
        content = content.replace(pattern.regex, replacement);
        
        if (content !== originalContent) {
          fileModified = true;
          if (verbose) {
            console.log(`  ✅ Updated pattern in ${fileConfig.file}`);
          }
        }
      }
      
      if (fileModified) {
        if (!dryRun) {
          await fs.writeFile(filePath, content, 'utf8');
        }
        updatedFiles.push(fileConfig.file);
        
        if (verbose) {
          console.log(`  📝 ${dryRun ? 'Would update' : 'Updated'}: ${fileConfig.file}`);
        }
      }
    }
    
    if (dryRun) {
      console.log(`\\n🔍 DRY RUN: Would update ${updatedFiles.length} files`);
      console.log('Files that would be updated:', updatedFiles);
    } else {
      console.log(`\\n✅ Version update completed! Updated ${updatedFiles.length} files.`);
      console.log('Updated files:', updatedFiles);
      console.log('\\n📋 Next steps:');
      console.log('  1. Run tests: npm run test:smoke');
      console.log('  2. Deploy to local: npm run deploy:local');
      console.log('  3. Test functionality thoroughly');
      console.log('  4. Commit changes and create PR when ready');
    }
    
  } catch (error) {
    console.error('❌ Version update failed:', error.message);
    process.exit(1);
  }
}

/**
 * Increment version automatically
 */
function incrementVersion(currentVersion, type = 'patch') {
  if (!currentVersion) {
    throw new Error('Cannot increment: current version not found');
  }
  
  const parts = currentVersion.split('.');
  if (parts.length < 3) {
    throw new Error(`Invalid current version format: ${currentVersion}`);
  }
  
  let [major, minor, patch] = parts.map(Number);
  
  switch (type) {
    case 'major':
      major++;
      minor = 0;
      patch = 0;
      break;
    case 'minor':
      minor++;
      patch = 0;
      break;
    case 'patch':
    default:
      patch++;
      break;
  }
  
  return `${major}.${minor}.${patch}`;
}

// CLI setup
const argv = yargs
  .command('$0 [version]', 'Update plugin version in all relevant files', {
    version: {
      type: 'string',
      description: 'New version number (e.g., 2.0.4)'
    },
    increment: {
      alias: 'i',
      type: 'string',
      choices: ['major', 'minor', 'patch'],
      description: 'Increment current version automatically'
    },
    'dry-run': {
      alias: 'd',
      type: 'boolean',
      description: 'Show what would be updated without actually updating'
    },
    verbose: {
      alias: 'v',
      type: 'boolean',
      description: 'Show verbose output'
    }
  })
  .example('$0 2.0.4', 'Update to version 2.0.4')
  .example('$0 --increment patch', 'Increment patch version (2.0.3 → 2.0.4)')
  .example('$0 --increment minor', 'Increment minor version (2.0.3 → 2.1.0)')
  .help()
  .argv;

// Run version update if called directly
if (require.main === module) {
  (async () => {
    try {
      let newVersion = argv.version;
      
      if (argv.increment) {
        const currentVersion = await getCurrentVersion();
        newVersion = incrementVersion(currentVersion, argv.increment);
        console.log(`🔢 Auto-incrementing ${argv.increment} version: ${currentVersion} → ${newVersion}`);
      }
      
      if (!newVersion) {
        console.error('❌ Please specify a version number or use --increment');
        console.log('Examples:');
        console.log('  node scripts/update-version.js 2.0.4');
        console.log('  node scripts/update-version.js --increment patch');
        process.exit(1);
      }
      
      await updateVersion(newVersion, argv);
    } catch (error) {
      console.error('❌ Error:', error.message);
      process.exit(1);
    }
  })();
}

module.exports = { updateVersion, getCurrentVersion, incrementVersion };