<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SearchWordJob;
use Illuminate\Console\Command;

/**
 * docker compose exec -it billion-app php artisan queue:listen --timeout=120 --memory=192
 */
class SearchCommand extends Command
{
    public const CHUNK_SIZE = 1 * 1024 * 1024 * 192; // 192MB

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billion:search';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Search text in billion lines';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $start = microtime(true);
        $mem = memory_get_usage();

        // $this->search('34393939'); // 44.432281970978
        // $this->search('999999999'); // 1311.2446448803
        // $this->searchParallel('34393939'); // 79
        // $this->searchParallel('999999998'); // 1631
        $this->searchParallel('34393939'); // 900 // 10:44:19 -> 10:54:41 = 622 сек

        $this->line("Execution time: " . (microtime(true) - $start));
        $this->line("Used memory is: " . $this->formatBytes(memory_get_usage() - $mem));
    }

    private function searchParallel(string $word): void
    {
        $positions = [];

        $this->makeChunks($positions);

        $start = 0;
        foreach ($positions as $position) {
            dispatch(new SearchWordJob($word, $start, $position - $start));
            $this->line("SearchWordJob params: $word, $start, " . ($position - $start));
            $start = $position + 1;
        }
        dispatch(new SearchWordJob($word, $start, self::CHUNK_SIZE - 100));
        $this->line("SearchWordJob params: $word, $start, " . (self::CHUNK_SIZE - 100));
    }

    private function makeChunks(array &$positions): void
    {
        $fileName = "billion.txt";
        $size = filesize($fileName);
        $file = fopen($fileName, "r") or die("Unable to open file!");

        $step = self::CHUNK_SIZE - 100;
        for ($i = $step; $i < $size; $i = $i + $step) {
            fseek($file, $i);

            if (fgets($file) === false) {
                $this->line("End of file");
                break;
            }

            $positions[] = ftell($file);
        }

        fclose($file);
    }

    private function search(string $word): void
    {
        $bufferSize = 0;

        $chunksCount = 1;

        $lastPosition = 0;

        $isFound = false;

        while ($lastPosition !== false) {
            $this->searchInPart($word, $lastPosition, $bufferSize, $chunksCount, $isFound);
            if ($isFound) {
                break;
            }
        }
    }

    private function searchInPart($word, &$lastPosition, &$bufferSize, &$chunksCount, &$isFound): void
    {
        $file = fopen("billion.txt", "r") or die("Unable to open file!");
        fseek($file, $lastPosition + 1);
        if ($file) {
            while (!feof($file)) {
                $line = trim(fgets($file));
                if ($line === false) {
                    $this->line("Word not found: $word!");
                    break;
                }

                $bufferSize += mb_strlen($line, "8bit");

                if ($line === $word) {
                    $this->line("Found word: $word!");
                    $isFound = true;
                    break;
                }

                if ($bufferSize >= (self::CHUNK_SIZE * $chunksCount)) {
                    $chunksCount++;
                    $bufferSize = 0;
                    break;
                }
            }
        }
        $lastPosition = ftell($file);
        fclose($file);
    }

    private function formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = ['', 'K', 'M', 'G', 'T'];

        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }
}
