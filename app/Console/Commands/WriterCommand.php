<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WriterCommand extends Command
{
    public const COUNT = 10000000;

    public const BATCH_SIZE = 1000000;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billion:write';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Write billion lines';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
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
            $lines[] = "" . rand(0, self::BATCH_SIZE);

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
