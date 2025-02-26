<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Support\Collection;

class SortJob extends Job
{
    public const BATCH_SIZE = 1000000;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        private int $index,
        private int $start,
        private int $lenght
    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $lines = new Collection();

        $this->readFile($lines);

        if ($lines = $lines->sort()) {
            $this->writeByBatchByFopen($this->index, $lines);
        }
    }

    private function readFile($lines): void
    {
        $file = fopen("billion.txt", "r") or die("Unable to open file!");
        fseek($file, $this->start + 1);
        if ($file) {
            $bufferSize = 0;
            while (!feof($file)) {
                $line = fgets($file);
                if ($line === false) {
                    break;
                }

                $bufferSize += mb_strlen($line, "8bit");
                
                $lines->push(intval(trim($line)));

                if ($bufferSize >= $this->lenght) {
                    break;
                }
            }
        }
        fclose($file);
    }

    private function writeByBatchByFopen(int $index, Collection $lines): void
    {
        $file = fopen("part_$index.txt", "w") or die("Unable to open file!");

        $tmp = [];
        for ($i = 0; $i < $lines->count(); $i++) {
            $tmp[] = $lines[$i];
            if ((($i + 1) % self::BATCH_SIZE) === 0) {
                fwrite($file, implode("\n", $tmp) . "\n");
                $tmp = [];
            }
        }

        if ($tmp !== []) {
            fwrite($file, implode("\n", $tmp));
        }

        fclose($file);
    }
}
