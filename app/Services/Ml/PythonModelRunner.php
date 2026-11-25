<?php

namespace App\Services\Ml;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Exécute un script Python d'entraînement ou de prédiction.
 */
abstract class PythonModelRunner
{
    abstract protected function script(): string;

    /**
     * @return array<string, mixed>
     */
    public function train(string $datasetPath, string $modelPath, string $metricsPath): array
    {
        return $this->run([
            'train',
            '--input', $datasetPath,
            '--model-out', $modelPath,
            '--metrics-out', $metricsPath,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function predict(string $modelPath, string $inputPath, string $outputPath): array
    {
        return $this->run([
            'predict',
            '--model', $modelPath,
            '--input', $inputPath,
            '--output', $outputPath,
        ]);
    }

    public function available(): bool
    {
        $process = new Process([$this->python(), '--version']);
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    private function run(array $arguments): array
    {
        $script = base_path($this->script());

        if (! is_file($script)) {
            throw new RuntimeException("Script ML introuvable : {$script}");
        }

        $process = new Process(array_merge([$this->python(), $script], $arguments));
        $process->setTimeout((int) config('ml.timeout', 180));
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            throw new RuntimeException('Le script ML a échoué : '.($error !== '' ? $error : 'erreur inconnue'));
        }

        $decoded = json_decode(trim($process->getOutput()), true);

        return is_array($decoded) ? $decoded : ['output' => trim($process->getOutput())];
    }

    private function python(): string
    {
        return (string) config('ml.python', 'python3');
    }
}
