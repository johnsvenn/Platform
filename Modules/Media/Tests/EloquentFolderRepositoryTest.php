<?php

namespace Modules\Media\Tests;

use Illuminate\Support\Facades\Event;
use Modules\Media\Entities\File;
use Modules\Media\Events\FolderIsCreating;
use Modules\Media\Events\FolderWasCreated;
use Modules\Media\Repositories\FolderRepository;

final class EloquentFolderRepositoryTest extends MediaTestCase
{
    /**
     * @var FolderRepository
     */
    private $folder;

    public function setUp()
    {
        parent::setUp();

        $this->resetDatabase();

        $this->folder = app(FolderRepository::class);
        $this->app['config']->set('asgard.media.config.files-path', '/assets/media/');
    }

    public function tearDown()
    {
        if ($this->app['files']->isDirectory(public_path('assets')) === true) {
            $this->app['files']->deleteDirectory(public_path('assets'));
        }
    }

    /** @test */
    public function it_can_create_a_folder_in_database()
    {
        $folder = $this->folder->create(['name' => 'My Folder', 'parent_id' => 0]);

        $this->assertCount(1, $this->folder->all());
        $this->assertEquals('My Folder', $folder->filename);
        $this->assertEquals('/assets/media/my-folder', $folder->path->getRelativeUrl());
        $this->assertTrue( $folder->is_folder);
        $this->assertTrue( $folder->isFolder());
        $this->assertEquals(0, $folder->folder_id);
    }

    /** @test */
    public function it_triggers_event_on_created_folder()
    {
        Event::fake();

        $folder = $this->folder->create(['name' => 'My Folder']);

        Event::assertDispatched(FolderWasCreated::class, function ($e) use ($folder) {
            return $e->folder->id === $folder->id;
        });
    }

    /** @test */
    public function it_triggers_an_event_when_folder_is_creating()
    {
        Event::fake();

        $folder = $this->folder->create(['name' => 'My Folder']);

        Event::assertDispatched(FolderIsCreating::class, function ($e) use ($folder) {
            return $e->getAttribute('filename') === $folder->filename;
        });
    }

    /** @test */
    public function it_can_change_folder_data_before_creating_folder()
    {
        Event::listen(FolderIsCreating::class, function (FolderIsCreating $event) {
            $filename = $event->getAttribute('filename');
            $event->setAttributes(['filename' => strtoupper($filename)]);
        });

        $folder = $this->folder->create(['name' => 'My Folder']);

        $this->assertEquals('MY FOLDER', $folder->filename);
    }

    /** @test */
    public function it_can_create_folder_on_disk()
    {
        $this->folder->create(['name' => 'My Folder']);

        $this->assertTrue($this->app['files']->isDirectory(public_path('assets/media/my-folder')));
    }

    /** @test */
    public function it_can_find_a_folder()
    {
        $this->folder->create(['name' => 'My Folder']);

        $folder = $this->folder->findFolder(1);

        $this->assertInstanceOf(File::class, $folder);
        $this->assertEquals(1, $folder->id);
    }

    /** @test */
    public function it_can_create_folders_belonging_to_another_folder()
    {
        $this->folder->create(['name' => 'Root Folder']);
        $childFolder = $this->folder->create(['name' => 'Child folder', 'parent_id' => 1]);

        $this->assertEquals('/assets/media/root-folder/child-folder', $childFolder->path->getRelativeUrl());
        $this->assertTrue($this->app['files']->isDirectory(public_path('assets/media/root-folder/child-folder')));
    }

    private function resetDatabase()
    {
        // Makes sure the migrations table is created
        $this->artisan('migrate', [
            '--database' => 'sqlite',
        ]);
        // We empty all tables
        $this->artisan('migrate:reset', [
            '--database' => 'sqlite',
        ]);
        // Migrate
        $this->artisan('migrate', [
            '--database' => 'sqlite',
        ]);

        $this->artisan('migrate', [
            '--database' => 'sqlite',
            '--path'     => 'Modules/Tag/Database/Migrations',
        ]);
    }
}
