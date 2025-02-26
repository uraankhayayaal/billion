<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SortJob;
use Illuminate\Console\Command;

class SorterCommand extends Command
{
    public const CHUNK_SIZE = 1 * 1024 * 1024 * 13; // 192MB

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
        $start = microtime(true);

        $this->readFile();

        $end = microtime(true) - $start;

        $this->newLine();
        $this->line("Execution time: $end");
    }

    private function readFile(): void
    {
        $positions = [];

        $this->makeChunks($positions);

        $start = 0;
        foreach ($positions as $index => $position) {
            dispatch(new SortJob($index, $start, $position - $start));
            $this->line("SortJob params: $index, $start, " . ($position - $start));
            $start = $position + 1;
        }
        dispatch(new SortJob(count($positions), $start, $start + self::CHUNK_SIZE - 100));
        $this->line("SortJob params: " . count($positions) . ", $start, " . ($start + self::CHUNK_SIZE - 100));
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
}
