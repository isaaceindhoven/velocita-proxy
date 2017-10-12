<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class StorageLinkRepoCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'storage:link-repo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a symbolic link from "public/repo" to "storage/app/repo"';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (file_exists(public_path('repo'))) {
			return;
        }

        $this->laravel->make('files')->link(storage_path('app/repo'), public_path('repo'));

        $this->info('The [public/repo] directory has been linked.');
    }
}
