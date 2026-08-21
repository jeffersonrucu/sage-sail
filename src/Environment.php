<?php

namespace Sage\Sail;

class Environment
{
    private string $contents;

    public function __construct(private readonly string $path)
    {
        $this->contents = (string) file_get_contents($path);
    }

    public function get(string $key): ?string
    {
        if (preg_match('/^[ \t]*' . preg_quote($key, '/') . '[ \t]*=[ \t]*(.*)$/m', $this->contents, $matches) !== 1) {
            return null;
        }

        return trim($matches[1], " \t\"'");
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Set a variable, uncommenting and replacing an existing definition when present.
     */
    public function set(string $key, string $value): void
    {
        $line = $key . '=' . $this->format($value);
        $pattern = '/^[ \t]*#?[ \t]*' . preg_quote($key, '/') . '[ \t]*=.*$/m';
        $count = 0;

        // A callback keeps "$" and "\" in the value from being read as backreferences...
        $replaced = preg_replace_callback($pattern, static fn (): string => $line, $this->contents, 1, $count);

        if ($replaced !== null && $count > 0) {
            $this->contents = $replaced;

            return;
        }

        $this->contents = rtrim($this->contents, "\n") . "\n" . $line . "\n";
    }

    public function save(): void
    {
        file_put_contents($this->path, $this->contents);
    }

    /**
     * Quote values that would otherwise break dotenv parsing.
     */
    private function format(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_.\-:\/]*$/', $value) === 1) {
            return $value;
        }

        // Single quotes suppress interpolation, so references need double quotes...
        return str_contains($value, '${')
            ? '"' . $value . '"'
            : "'" . $value . "'";
    }
}
