<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Plan;

/**
 * What happened during an apply, and what refit could not resolve.
 *
 * The warnings are the honest half of the tool: an icon with no translation or a
 * file that was already gone is recorded here rather than silently skipped.
 */
final class Report
{
    /**
     * @var list<string>
     */
    private array $notes = [];

    /**
     * @var list<string>
     */
    private array $warnings = [];

    /**
     * @var list<string>
     */
    private array $changed = [];

    public function note(string $message): void
    {
        $this->notes[] = $message;
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * Record that a project-relative path was written, so the Blade guard knows
     * what to re-check without walking the whole tree.
     */
    public function changed(string $path): void
    {
        if (! in_array($path, $this->changed, true)) {
            $this->changed[] = $path;
        }
    }

    /**
     * @return list<string>
     */
    public function notes(): array
    {
        return $this->notes;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return list<string>
     */
    public function changedFiles(): array
    {
        return $this->changed;
    }

    /**
     * Take the paths written since the last call, and reset the buffer.
     *
     * @return list<string>
     */
    public function drainChanged(): array
    {
        $changed = $this->changed;

        $this->changed = [];

        return $changed;
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    /**
     * Render the report as a Markdown document for REFIT-NOTES.md.
     */
    public function toMarkdown(): string
    {
        $lines = ['# Refit notes', ''];

        if ($this->notes !== []) {
            $lines[] = '## What changed';
            $lines[] = '';

            foreach ($this->notes as $note) {
                $lines[] = '- '.$note;
            }

            $lines[] = '';
        }

        if ($this->warnings !== []) {
            $lines[] = '## Needs a look';
            $lines[] = '';

            foreach ($this->warnings as $warning) {
                $lines[] = '- '.$warning;
            }

            $lines[] = '';
        }

        return implode(PHP_EOL, $lines);
    }
}
