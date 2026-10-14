<?php

namespace Mail2Deck;

use League\HTMLToMarkdown\HtmlConverter;

class ConvertToMD {
    protected $html;
    protected $converter;

    public function __construct($html) {
        $this->converter = new HtmlConverter([
            'strip_tags' => true,
            'remove_nodes' => 'head style script title meta'
        ]);
        $this->html = $html;
    }

    public function execute()
    {
        $markdown = $this->converter->convert($this->html);

        return $this->cleanupMarkdown($markdown);
    }

    protected function cleanupMarkdown($markdown)
    {
        $markdown = html_entity_decode($markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $markdown = preg_replace("/\r\n?/", "\n", $markdown);
        $markdown = $this->stripQuotedReplies($markdown);
        $markdown = $this->stripSignature($markdown);
        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown);

        return trim($markdown);
    }

    protected function stripQuotedReplies($markdown)
    {
        $patterns = [
            "/\n-{2,}\s*Forwarded message\s*-{2,}\s*\n/i",
            "/\nOn .+ wrote:\s*\n/i",
            "/\n(?:\*\*)?From:(?:\*\*)?[^\n]*(?:\n(?:\*\*)?(?:Sent|Date|To|Cc|Subject):(?:\*\*)?[^\n]*){2,}/i",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $markdown, $matches, PREG_OFFSET_CAPTURE)) {
                return trim(substr($markdown, 0, $matches[0][1]));
            }
        }

        return trim($markdown);
    }

    protected function stripSignature($markdown)
    {
        $lines = explode("\n", trim($markdown));
        $lineCount = count($lines);

        if ($lineCount < 4) {
            return trim($markdown);
        }

        for ($index = 2; $index < $lineCount; $index++) {
            if (!$this->isSignatureBoundary($lines, $index)) {
                continue;
            }

            $tail = array_slice($lines, $index);
            if ($this->signatureScore($tail) >= 4) {
                return trim(implode("\n", array_slice($lines, 0, $index)));
            }
        }

        return trim($markdown);
    }

    protected function isSignatureBoundary(array $lines, $index)
    {
        $current = trim($lines[$index]);
        $previous = trim($lines[$index - 1]);

        if ($current === '--' || $current === '-- ') {
            return true;
        }

        if (preg_match('/^[_-]{5,}$/', $current)) {
            return true;
        }

        if ($previous !== '' || $current === '') {
            return false;
        }

        if ($this->looksLikeSignatureLine($current)) {
            return true;
        }

        return preg_match("/^[A-Z][\\p{L}'-]+(?:\\s+[A-Z][\\p{L}'-]+){1,3}$/u", $current)
            && $this->signatureScore(array_slice($lines, $index, 6)) >= 2;
    }

    protected function looksLikeSignatureLine($line)
    {
        return (bool) preg_match(
            '/\b(LinkedIn|Facebook|Instagram|Twitter|YouTube|X|tel:|mob:|phone:|mobile:|ltd\.?|limited|inc\.?|llc|administrator|officer|manager|director|department|team)\b|https?:\/\/|www\.|\[[^\]]+\]\((?:https?:\/\/|<https?:\/\/)|@[A-Z0-9._%+-]+\.[A-Z]{2,}|(?:^|[| ])(?:T|M)\d|cid:/i',
            $line
        );
    }

    protected function signatureScore(array $lines)
    {
        $text = implode("\n", $lines);
        $score = 0;

        if (preg_match('/\b(confidential|received this in error|registered company|registered office|registered charity)\b/i', $text)) {
            $score += 3;
        }

        if (preg_match('/\b(LinkedIn|Facebook|Instagram|Twitter|YouTube|X)\b|\[[^\]]+\]\((?:https?:\/\/|<https?:\/\/)/i', $text)) {
            $score += 2;
        }

        if (preg_match('/\b(?:tel:|mob:|phone:|mobile:|T\d|M\d|\+\d{2,})/i', $text)) {
            $score += 1;
        }

        if (preg_match('/https?:\/\/|www\.|@[A-Z0-9._%+-]+\.[A-Z]{2,}/i', $text)) {
            $score += 1;
        }

        if (preg_match('/\b(ltd\.?|limited|inc\.?|llc|administrator|officer|manager|director|department|team)\b/i', $text)) {
            $score += 1;
        }

        if (preg_match('/^[_-]{5,}$/m', $text)) {
            $score += 1;
        }

        if (count($lines) >= 5) {
            $score += 1;
        }

        return $score;
    }
}
