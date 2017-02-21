<?php

namespace Encore\Admin\Form\Field;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\MessageBag;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait UploadField
{
    /**
     * Upload directory.
     *
     * @var string
     */
    protected $directory = '';

    /**
     * File name.
     *
     * @var null
     */
    protected $name = null;

    /**
     * Options for file-upload plugin.
     *
     * @var array
     */
    protected $options = [];

    /**
     * Storage instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $storage = '';

    /**
     * If use unique name to store upload file.
     *
     * @var bool
     */
    protected $useUniqueName = false;

    /**
     * Create a new File instance.
     *
     * @param string $column
     * @param array $arguments
     */
    public function __construct($column, $arguments = [])
    {
        $this->initStorage();

        parent::__construct($column, $arguments);
    }

    /**
     * Initialize the storage instance.
     *
     * @return void.
     */
    protected function initStorage()
    {
        $this->disk(config('admin.upload.disk'));
    }

    public function setupDefaultOptions()
    {
        $this->options([
            'overwriteInitial'     => false,
            'initialPreviewAsData' => true,
            'browseLabel'          => trans('admin::lang.browse'),
            'showRemove'           => false,
            'showUpload'           => false,
            'initialCaption'   => $this->initialCaption($this->value),
            'deleteUrl'        => $this->form->resource() . '/'. $this->form->model()->getKey(),
            'deleteExtraData'  => [
                $this->column       => '',
                static::FILE_DELETE_FLAG => '',
                '_token'            => csrf_token(),
                '_method'           => 'PUT'
            ]
        ]);
    }

    public function setupPreviewOptions()
    {
        $this->options([
            'initialPreview'        => $this->preview(),
            'initialPreviewConfig'  => $this->initialPreviewConfig(),
        ]);
    }

    /**
     * Set options for file-upload plugin.
     *
     * @param array $options
     *
     * @return $this
     */
    public function options($options = [])
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * Set disk for storage.
     *
     * @param string $disk Disks defined in `config/filesystems.php`.
     *
     * @return $this
     */
    public function disk($disk)
    {
        if (!array_key_exists($disk, config('filesystems.disks'))) {
            $error = new MessageBag([
                'title'   => 'Config error.',
                'message' => "Disk [$disk] not configured, please add a disk config in `config/filesystems.php`.",
            ]);

            return session()->flash('error', $error);
        }

        $this->storage = Storage::disk($disk);

        return $this;
    }

    /**
     * Specify the directory and name for uplaod file.
     *
     * @param string $directory
     * @param null|string $name
     *
     * @return $this
     */
    public function move($directory, $name = null)
    {
        $this->directory = $directory;

        $this->name = $name;

        return $this;
    }

    /**
     * Set name of store name.
     *
     * @param string|callable $name
     *
     * @return $this
     */
    public function name($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Use unique name for store upload file.
     *
     * @return $this
     */
    public function uniqueName()
    {
        $this->useUniqueName = true;

        return $this;
    }

    /**
     * Get store name of upload file.
     *
     * @param UploadedFile $file
     *
     * @return string
     */
    protected function getStoreName(UploadedFile $file)
    {
        if ($this->useUniqueName) {
            return $this->generateUniqueName($file);
        }

        if (is_callable($this->name)) {
            $callback = $this->name->bindTo($this);

            return call_user_func($callback, $file);
        }

        if (is_string($this->name)) {
            return $this->name;
        }

        return $file->getClientOriginalName();
    }

    /**
     * Get directory for store file.
     *
     * @return mixed|string
     */
    public function getDirectory()
    {
        if ($this->directory instanceof \Closure) {
            return call_user_func($this->directory, $this->form);
        }

        return $this->directory ?: $this->defaultDirectory();
    }

    /**
     * Upload file and delete original file.
     *
     * @param UploadedFile $file
     *
     * @return mixed
     */
    protected function upload(UploadedFile $file)
    {
        $this->renameIfExists($file);

        $target = $this->getDirectory() . '/' . $this->name;

        $this->storage->put($target, file_get_contents($file->getRealPath()));

        return $target;
    }

    /**
     * If name already exists, rename it.
     *
     * @param $file
     *
     * @return void
     */
    public function renameIfExists(UploadedFile $file)
    {
        if ($this->storage->exists("{$this->getDirectory()}/$this->name")) {
            $this->name = $this->generateUniqueName($file);
        }
    }

    /**
     * Get file visit url.
     *
     * @param $path
     *
     * @return string
     */
    public function objectUrl($path)
    {
        if (URL::isValidUrl($path)) {
            return $path;
        }

        return rtrim(config('admin.upload.host'), '/') . '/' . trim($path, '/');
    }

    /**
     * Generate a unique name for uploaded file.
     *
     * @param UploadedFile $file
     *
     * @return string
     */
    protected function generateUniqueName(UploadedFile $file)
    {
        return md5(uniqid()).'.'.$file->guessExtension();
    }

    /**
     * Destroy original files.
     *
     * @return void.
     */
    public function destroy()
    {
        if ($this->storage->exists($this->original)) {
            $this->storage->delete($this->original);
        }
    }
}
