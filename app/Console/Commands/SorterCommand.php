<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SorterCommand extends Command
{
    public const CHUNK_SIZE = 1 * 1024 * 1024 * 192; // 192MB

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billion:sort';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sort billion lines';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // 1. Read big file by part
        // 2. Sort each parts and save into another files
        // 3. Merge sort parts

        $bar = $this->output->createProgressBar(self::COUNT);

        $bar->start();
        $start = microtime(true);

        // $this->writeLineByLine($bar); // 1000000 -> 28.808171033859
        // $this->writeOnceByFilePutContents($bar); // 1000000 -> 0.18702411651611
        // $this->writeOnceByFopen($bar); // 1000000 -> 0.18361592292786
        // $this->writeByBatchByFilePutContents($bar); // 1000000 -> 0.25188207626343 1000000000 -> 462.08634614944
        $this->writeByBatchByFopen($bar); // 1000000 -> 0.28225898742676 1000000000 -> 267.176197052

        $end = microtime(true) - $start;
        $bar->finish();

        $this->newLine();
        $this->line("Execution time: $end");
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
        dispatch(new SearchWordJob($word, $start, $start + self::CHUNK_SIZE - 100));
        $this->line("SearchWordJob params: $word, $start, " . ($start + self::CHUNK_SIZE - 100));
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

    private function writeLineByLine($bar): void
    {
        $file = fopen("billion.txt", "w") or die("Unable to open file!");

        for ($i = 0; $i < self::COUNT; $i++) {
            fwrite($file, "$i\n");
        }

        $bar->advance(self::COUNT);

        fclose($file);
    }

    private function writeOnceByFopen($bar): void
    {
        $file = fopen("billion.txt", "w") or die("Unable to open file!");

        $lines = '';

        for ($i = 0; $i < self::COUNT; $i++) {
            $lines .= "$i\n";
        }

        $bar->advance(self::COUNT);

        fwrite($file, $lines);

        fclose($file);
    }

    private function writeOnceByFilePutContents($bar): void
    {
        file_exists('billion.txt') && unlink('billion.txt');

        $lines = '';

        for ($i = 0; $i < self::COUNT; $i++) {
            $lines .= "$i\n";
        }

        $bar->advance(self::COUNT);

        file_put_contents('billion.txt', $lines, FILE_APPEND);
    }

    private function writeByBatchByFilePutContents($bar): void
    {
        file_exists('billion.txt') && unlink('billion.txt');

        $lines = [];

        for ($i = 0; $i < self::COUNT; $i++) {
            $lines[] = "$i";

            if ((($i + 1) % self::BATCH_SIZE) === 0) {
                file_put_contents('billion.txt', implode("\n", $lines) . "\n", FILE_APPEND);
                $lines = [];
                $bar->advance(self::BATCH_SIZE);
            }
        }

        if ($lines !== []) {
            file_put_contents('billion.txt', implode("\n", $lines), FILE_APPEND);
        }
    }

    private function writeByBatchByFopen($bar): void
    {
        $file = fopen("billion.txt", "w") or die("Unable to open file!");

        $lines = [];

        for ($i = 0; $i < self::COUNT; $i++) {
            $lines[] = "$i";

            if ((($i + 1) % self::BATCH_SIZE) === 0) {
                fwrite($file, implode("\n", $lines) . "\n");
                $lines = [];
                $bar->advance(self::BATCH_SIZE);
            }
        }

        if ($lines !== []) {
            fwrite($file, implode("\n", $lines));
        }

        fclose($file);
    }
}
