<?php

namespace App\Services\Exports;

use App\Exceptions\ExportFailed;
use App\Models\StoredFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Writes one export ZIP (12 §3): files are added by path and read from disk when the archive is
 * closed, so memory stays flat. Entry names are sanitised and a repeated name gets " (2)".
 * Files on a non-local disk are copied to temporary files first, flushed every FLUSH_EVERY.
 */
class ZipWriter
{
    private const FLUSH_EVERY = 200;

    /** Already compressed: stored as is, which is much faster. */
    private const STORED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'zip', 'docx', 'xlsx'];

    private ZipArchive $zip;

    private int $bytes = 0;

    private int $entries = 0;

    /** @var array<string, true> lower-cased entry names in use */
    private array $names = [];

    /** @var list<string> */
    private array $temporary = [];

    /** @var list<string> */
    private array $missing = [];

    public function __construct(private string $path, private int $maxBytes)
    {
        $this->zip = new ZipArchive;
        $this->open(ZipArchive::CREATE | ZipArchive::OVERWRITE);
    }

    /**
     * @param  list<string>  $folders  unsanitised directory names
     */
    public function add(array $folders, string $name, string $extension, StoredFile $file): void
    {
        $entry = $this->entryName($folders, $name, $extension);
        $this->bytes += $file->size_bytes;

        if ($this->bytes > $this->maxBytes) {
            throw new ExportFailed('This export is larger than '.round($this->maxBytes / 1024 ** 3, 2).' GB. Export single drivers instead.');
        }

        $source = $this->localCopy($file);

        if ($source === null) {
            $this->missing[] = $entry;

            return;
        }

        $this->zip->addFile($source, $entry) ?: throw new RuntimeException("Could not add {$entry} to the ZIP.");

        if (in_array(strtolower($extension), self::STORED_EXTENSIONS, true)) {
            $this->zip->setCompressionName($entry, ZipArchive::CM_STORE);
        }

        $this->entries++;

        if (count($this->temporary) >= self::FLUSH_EVERY) {
            $this->flush();
        }
    }

    /**
     * Writes the archive. Files that could not be read are listed in "Missing files.txt".
     */
    public function close(): void
    {
        if ($this->missing !== []) {
            $this->addText('Missing files', "These files are recorded but could not be read from storage:\n\n".implode("\n", $this->missing)."\n");
        }

        if ($this->entries === 0 && $this->missing === []) {
            $this->addText('No documents', "No documents were found for this export.\n");
        }

        $this->zip->close() ?: throw new RuntimeException('Could not write the ZIP: '.$this->zip->getStatusString());
        $this->cleanup();
    }

    public function entries(): int
    {
        return $this->entries;
    }

    public function cleanup(): void
    {
        foreach ($this->temporary as $path) {
            @unlink($path);
        }

        $this->temporary = [];
    }

    /**
     * @param  list<string>  $folders
     */
    private function entryName(array $folders, string $name, string $extension): string
    {
        $directory = implode('/', array_map(self::sanitise(...), $folders));
        $base = self::sanitise($name);
        $extension = preg_replace('/[^a-z0-9]/', '', strtolower($extension));
        $suffix = $extension === '' ? '' : ".{$extension}";

        for ($n = 1; ; $n++) {
            $entry = ($directory === '' ? '' : "{$directory}/").$base.($n === 1 ? '' : " ({$n})").$suffix;

            if (! isset($this->names[mb_strtolower($entry)])) {
                $this->names[mb_strtolower($entry)] = true;

                return $entry;
            }
        }
    }

    /**
     * One path segment: no separators, reserved or control characters, no leading or trailing
     * dots and spaces (so never "." or ".."), at most 100 characters.
     */
    public static function sanitise(string $segment): string
    {
        $clean = (string) preg_replace('/[\/\\\\:*?"<>|\x00-\x1F\x7F]+/u', '-', $segment);
        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean), " .\t");

        return mb_substr($clean, 0, 100) ?: 'file';
    }

    /**
     * The file's local path: the object itself on a local disk, else a temporary copy.
     */
    private function localCopy(StoredFile $file): ?string
    {
        $disk = Storage::disk($file->disk);

        if (config("filesystems.disks.{$file->disk}.driver") === 'local') {
            $path = $disk->path($file->path);

            return is_file($path) ? $path : null;
        }

        $stream = $disk->readStream($file->path);

        if ($stream === null) {
            return null;
        }

        $copy = (string) tempnam(sys_get_temp_dir(), 'dq-zip-file-');
        $this->temporary[] = $copy;
        $target = fopen($copy, 'wb') ?: throw new RuntimeException('Could not create a temporary file.');
        stream_copy_to_stream($stream, $target);
        fclose($target);
        fclose($stream);

        return $copy;
    }

    private function addText(string $name, string $contents): void
    {
        $this->zip->addFromString($this->entryName([], $name, 'txt'), $contents);
    }

    /**
     * Close and reopen so the queued temporary copies are written and can be deleted.
     */
    private function flush(): void
    {
        $this->zip->close() ?: throw new RuntimeException('Could not write the ZIP: '.$this->zip->getStatusString());
        $this->cleanup();
        $this->open(ZipArchive::CREATE);
    }

    private function open(int $flags): void
    {
        if ($this->zip->open($this->path, $flags) !== true) {
            throw new RuntimeException('Could not open the ZIP for writing.');
        }
    }
}
