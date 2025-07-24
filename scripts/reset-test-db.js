#!/usr/bin/env node

const fs = require('fs-extra');
const path = require('path');
const { exec } = require('child_process');
const { promisify } = require('util');
const yargs = require('yargs/yargs');
const { hideBin } = require('yargs/helpers');

const execAsync = promisify(exec);

// Configuration
const CONFIG = {
  localSqlPath: '/Users/chadmacbook/Local Sites/handy-crab/app/sql/local.sql',
  localSiteUrl: 'http://localhost:10008',
  backupDir: path.resolve(__dirname, '../tests/data/db-backups'),
  mysqlConfig: {
    host: 'localhost',
    port: '10004', // Default Local MySQL port for handy-crab site
    database: 'local', // Default Local database name
    username: 'root',
    password: 'root' // Default Local MySQL password
  }
};

/**
 * Execute MySQL command
 */
async function executeMysqlCommand(command, options = {}) {
  const { verbose = false } = options;
  
  const mysqlCmd = `mysql -h ${CONFIG.mysqlConfig.host} -P ${CONFIG.mysqlConfig.port} -u ${CONFIG.mysqlConfig.username} -p${CONFIG.mysqlConfig.password} ${CONFIG.mysqlConfig.database} -e "${command}"`;
  
  if (verbose) {
    console.log(`🔧 Executing: ${command}`);
  }
  
  try {
    const { stdout, stderr } = await execAsync(mysqlCmd);
    if (stderr && verbose) {
      console.log('MySQL output:', stderr);
    }
    return stdout;
  } catch (error) {
    throw new Error(`MySQL command failed: ${error.message}`);
  }
}

/**
 * Create a database backup
 */
async function createBackup(name, options = {}) {
  const { verbose = false } = options;
  
  try {
    await fs.ensureDir(CONFIG.backupDir);
    
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const backupName = name || `backup-${timestamp}`;
    const backupFile = path.join(CONFIG.backupDir, `${backupName}.sql`);
    
    console.log(`💾 Creating database backup: ${backupName}`);
    
    const mysqldumpCmd = `mysqldump -h ${CONFIG.mysqlConfig.host} -P ${CONFIG.mysqlConfig.port} -u ${CONFIG.mysqlConfig.username} -p${CONFIG.mysqlConfig.password} ${CONFIG.mysqlConfig.database} > "${backupFile}"`;
    
    await execAsync(mysqldumpCmd);
    
    console.log(`✅ Backup created: ${backupFile}`);
    return backupFile;
    
  } catch (error) {
    throw new Error(`Failed to create backup: ${error.message}`);
  }
}

/**
 * Restore database from backup or original SQL file
 */
async function restoreDatabase(source, options = {}) {
  const { verbose = false, createBackup: shouldBackup = true } = options;
  
  try {
    // Create backup before restore
    if (shouldBackup) {
      await createBackup('pre-restore-backup', { verbose });
    }
    
    let sqlFile = source;
    
    // If no source specified, use original Local SQL file
    if (!source) {
      sqlFile = CONFIG.localSqlPath;
      console.log(`🔄 Restoring from original Local SQL file...`);
    } else if (!path.isAbsolute(source)) {
      // Check if it's a backup name
      sqlFile = path.join(CONFIG.backupDir, `${source}.sql`);
      if (!await fs.pathExists(sqlFile)) {
        throw new Error(`Backup file not found: ${sqlFile}`);
      }
      console.log(`🔄 Restoring from backup: ${source}`);
    } else {
      console.log(`🔄 Restoring from file: ${source}`);
    }
    
    // Check if SQL file exists
    if (!await fs.pathExists(sqlFile)) {
      throw new Error(`SQL file not found: ${sqlFile}`);
    }
    
    if (verbose) {
      console.log(`📂 Source file: ${sqlFile}`);
    }
    
    // Import SQL file
    const mysqlCmd = `mysql -h ${CONFIG.mysqlConfig.host} -P ${CONFIG.mysqlConfig.port} -u ${CONFIG.mysqlConfig.username} -p${CONFIG.mysqlConfig.password} ${CONFIG.mysqlConfig.database} < "${sqlFile}"`;
    
    await execAsync(mysqlCmd);
    
    console.log(`✅ Database restored successfully`);
    console.log(`🌐 Site available at: ${CONFIG.localSiteUrl}`);
    
  } catch (error) {
    throw new Error(`Failed to restore database: ${error.message}`);
  }
}

/**
 * Reset specific WordPress data for testing
 */
async function resetTestData(options = {}) {
  const { verbose = false } = options;
  
  try {
    console.log('🧹 Resetting test data...');
    
    // Clear transients (cache)
    await executeMysqlCommand("DELETE FROM wp_options WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%';", { verbose });
    
    // Reset plugin options to defaults (if needed)
    // await executeMysqlCommand("DELETE FROM wp_options WHERE option_name LIKE 'handy_custom_%';", { verbose });
    
    // Clear any test posts/pages that might interfere
    // Note: Be careful with this - you might want to be more specific
    // await executeMysqlCommand("DELETE FROM wp_posts WHERE post_title LIKE '%test%' AND post_type IN ('product', 'recipe');", { verbose });
    
    console.log('✅ Test data reset completed');
    
  } catch (error) {
    throw new Error(`Failed to reset test data: ${error.message}`);
  }
}

/**
 * List available backups
 */
async function listBackups() {
  try {
    await fs.ensureDir(CONFIG.backupDir);
    const files = await fs.readdir(CONFIG.backupDir);
    const sqlFiles = files.filter(file => file.endsWith('.sql'));
    
    if (sqlFiles.length === 0) {
      console.log('📁 No database backups found');
      return [];
    }
    
    console.log('📁 Available database backups:');
    
    const backups = [];
    for (const file of sqlFiles) {
      const filePath = path.join(CONFIG.backupDir, file);
      const stat = await fs.stat(filePath);
      const backupName = file.replace('.sql', '');
      
      backups.push({
        name: backupName,
        file: file,
        size: stat.size,
        created: stat.mtime
      });
      
      console.log(`  - ${backupName} (${(stat.size / 1024 / 1024).toFixed(2)} MB, ${stat.mtime.toLocaleDateString()})`);
    }
    
    return backups;
    
  } catch (error) {
    throw new Error(`Failed to list backups: ${error.message}`);
  }
}

/**
 * Test database connection
 */
async function testConnection(options = {}) {
  const { verbose = false } = options;
  
  try {
    console.log('🔌 Testing database connection...');
    
    const result = await executeMysqlCommand('SELECT VERSION() as version, DATABASE() as database, USER() as user;', { verbose });
    
    console.log('✅ Database connection successful');
    if (verbose) {
      console.log('Connection details:', result);
    }
    
    return true;
    
  } catch (error) {
    console.error('❌ Database connection failed:', error.message);
    console.log('\\n🔧 Troubleshooting:');
    console.log('  1. Make sure Local by WP Engine is running');
    console.log('  2. Check if handy-crab site is started');
    console.log('  3. Verify MySQL port (default: 10004)');
    console.log('  4. Check Local site settings for database credentials');
    return false;
  }
}

// CLI setup
const argv = yargs(hideBin(process.argv))
  .command('$0 [action]', 'Manage test database', (yargs) => {
    return yargs
      .positional('action', {
        type: 'string',
        choices: ['restore', 'backup', 'reset', 'list', 'test'],
        default: 'restore',
        description: 'Action to perform'
      })
      .option('source', {
        alias: 's',
        type: 'string',
        description: 'Backup name or SQL file path to restore from'
      })
      .option('name', {
        alias: 'n',
        type: 'string',
        description: 'Name for the backup'
      })
      .option('no-backup', {
        type: 'boolean',
        description: 'Skip creating backup before restore'
      })
      .option('verbose', {
        alias: 'v',
        type: 'boolean',
        description: 'Show verbose output'
      });
  })
  .example('$0 restore', 'Restore from original Local SQL file')
  .example('$0 backup --name clean-state', 'Create a named backup')
  .example('$0 restore --source clean-state', 'Restore from named backup')
  .example('$0 reset', 'Reset test data only')
  .example('$0 list', 'List available backups')
  .example('$0 test', 'Test database connection')
  .help()
  .argv;

// Run command if called directly
if (require.main === module) {
  (async () => {
    try {
      const { action, source, name, verbose } = argv;
      const createBackupFlag = !argv['no-backup'];
      
      switch (action) {
        case 'restore':
          await restoreDatabase(source, { verbose, createBackup: createBackupFlag });
          break;
          
        case 'backup':
          await createBackup(name, { verbose });
          break;
          
        case 'reset':
          await resetTestData({ verbose });
          break;
          
        case 'list':
          await listBackups();
          break;
          
        case 'test':
          await testConnection({ verbose });
          break;
          
        default:
          console.error(`Unknown action: ${action}`);
          process.exit(1);
      }
      
    } catch (error) {
      console.error('❌ Error:', error.message);
      process.exit(1);
    }
  })();
}

module.exports = { 
  createBackup, 
  restoreDatabase, 
  resetTestData, 
  listBackups, 
  testConnection 
};