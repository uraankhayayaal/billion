<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SearchWordJob extends Job
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        private string $word,
        private int $start,
        private int $size,
    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $pos = $this->isFoundedPosition();
        if ($pos) {
            Log::info("Word is founded at position: $pos!");
            return;
        }

        $file = fopen("billion.txt", "r") or die("Unable to open file!");
        fseek($file, $this->start);
        if ($file) {
            $bufferSize = 0;
            $isErrorToGetLineFromFile = false;
            $isFound = false;
            while (!feof($file)) {
                $this->search($file, $bufferSize, $isFound, $isErrorToGetLineFromFile);
                if ($isFound) {
                    Log::info("Found word: $this->word!");
                    $this->clearOtherJobs(ftell($file));
                    break;
                }
                if ($bufferSize >= $this->size) {
                    Log::info("Word not found: $this->word!");
                    break;
                }
                if ($isErrorToGetLineFromFile) {
                    Log::info("Word not found: $this->word!");
                    break;
                }
            }
        }
        fclose($file);
    }

    private function search($file, &$bufferSize, &$isFound, &$isErrorToGetLineFromFile): void
    {
        $line = fgets($file);
        if ($line === false) {
            $isErrorToGetLineFromFile = true;
            return;
        }

        $trimmed = trim($line);

        $bufferSize += mb_strlen($trimmed, "8bit");

        if ($trimmed === $this->word) {
            $isFound = true;
        }
    }

    private function clearOtherJobs(int $position): void
    {
        $position = $position - mb_strlen($this->word, "8bit");

        Redis::set('search:word:' . $this->word, $position, 'EX', 1 * 60 * 24);
    }

    private function isFoundedPosition(): ?string
    {
        $position = Redis::get('search:word:' . $this->word);

        return $position;
    }

    private function formatBytes($size, $precision = 2): string
    {
        $base = log($size, 1024);
        $suffixes = ['', 'K', 'M', 'G', 'T'];

        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }
}
