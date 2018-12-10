<?php
require 'vendor/deployer/deployer/recipe/common.php';
require 'vendor/deployphp/recipes/recipes/rsync.php';

// WHERE ARE BUILDS DONE
$workspaceDirectory = getenv('DEPLOYER_WORKSPACE');
if (!$workspaceDirectory) {
    $workspaceDirectory = __DIR__ . '/.build';
}

/**
 * Server list
 */
serverList('servers.yaml');

/**
 * Project repository
 */
set('repository', 'someone@git.mycompany.com');

/**
 * Common parameters.
 */
env('bin/composer', 'composer');
env('build_path', $workspaceDirectory);
env('rsync_src', $workspaceDirectory);
//set('keep_releases', 5);
//set('writable_use_sudo', false);
//set('clear_use_sudo', false);

/**
 * Shared directories
 */
set('shared_dirs', [
    'Data/Logs',
    'Data/Persistent',
    'Web/_Resources/Persistent',
    'Web/_Resources/downloads'
]);

/**
 * Init project
 */
task('project:init', function () {
    env('FLOW_CONTEXT', 'FLOW_CONTEXT=' . input()->getArgument('stage'));
    env('env_vars', '{{FLOW_CONTEXT}}');
    env('DEPLOYER_WORKSPACE',getenv('DEPLOYER_WORKSPACE'));

    //Configure rsync task
    set('rsync', [
        'exclude' => [],
        'exclude-file' => false,
        'include' => [],
        'include-file' => false,
        'filter' => [],
        'filter-file' => false,
        'filter-perdir' => false,
        'flags' => 'rzv',
        'options' => ['delete'],
        'timeout' => 3600,
    ]);
});

/**
 * Update project code
 */
task('project:update_code', function () {
    // TODO define branch/tag to get
})->desc('Updating code');

/**
 * Installing vendors tasks.
 */
task('project:composer', function () {
    runLocally("cd {{build_path}} && {{env_vars}} {{bin/composer}} {{composer_options}}", 1000);
})->desc('Installing composer locally');

/**
 * Flush and warmup cache
 */
task('flow:cache', function () {
    run('{{FLOW_CONTEXT}} {{release_path}}/flow flow:cache:warmup', 1000);
})->desc('Flush and warmup cache');

/**
 * Doctrine migrate
 */
task('migrate:doctrine', function () {
    run('{{FLOW_CONTEXT}} {{release_path}}/flow doctrine:migrate', 1000);
})->desc('Run database migrations');

/**
 * Migrate
 */
task('migrate', [
    'migrate:doctrine'
])->desc('Run all migration tasks');

/**
 * Publish resources
 */
task('resource:publish', function () {
    run('{{FLOW_CONTEXT}} {{release_path}}/flow resource:publish --collection static', 1000);
})->desc('Publish resources');

/**
 * Provision project
 */
task('project:provision', function () {
})->desc('Provision project');


/**
 * Change permission of LOCK files
 */
task('project:set_permissions_on_locks', function () {
    $contextParts = explode('/', input()->getArgument('stage'));
    $lockPath = '/Data/Temporary/' . array_shift($contextParts);
    foreach ($contextParts as $subContextPart) {
        $lockPath .= '/SubContext' . $subContextPart;
    }
    $lockPath .= '/Lock/*';

    run('{{FLOW_CONTEXT}} /bin/chmod 664 {{release_path}}' . $lockPath, 1000);
})->desc('Set permissions on lock files');

/**
 * Main task
 */
task('deploy', [
    // Init Local
    'project:init',
    'project:update_code',
    'project:composer',
    // Init Remote
    'deploy:prepare',
    'deploy:release',
    // Local > Remote
    'rsync',
    'flow:cache',
    'migrate',
    // Finish Remote
    'deploy:shared',
    'resource:publish',

    // Prepare Project
    'deploy:symlink',
    'project:provision',
    'cleanup',
//    'project:set_permissions_on_locks'
])->desc('Deploy project');
