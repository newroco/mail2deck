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

    public function execute($stripQuotedReplies = true)
    {
        $markdown = $this->converter->convert($this->html);

        return $this->cleanupMarkdown($markdown, $stripQuotedReplies);
    }

    protected function cleanupMarkdown($markdown, $stripQuotedReplies = true)
    {
        $markdown = html_entity_decode($markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $markdown = preg_replace("/\r\n?/", "\n", $markdown);

        if ($stripQuotedReplies) {
            $markdown = $this->stripQuotedReplies($markdown);
            $markdown = $this->stripSignature($markdown);
        }

        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown);

        return trim($markdown);
    }

    protected function stripQuotedReplies($markdown)
    {
        $patterns = [
            "/(?:^|\n)(?:\\\\)?[-*_]{2,}\s*Forwarded message\s*(?:\\\\)?[-*_]{2,}(?:\s*\n|$)/i",
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
        $normalized = preg_replace('/\r\n?/', "\n", trim($markdown));
        $patterns = [
            '/(?:^|\n)\s*(?:--|[-_]{5,})\s*(?:\n|$)/u',
            '/(?<![A-Za-z0-9])--\s*(?=[A-Z])/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized, $match, PREG_OFFSET_CAPTURE)) {
                $offset = $match[0][1];
                $suffix = trim(substr($normalized, $offset + strlen($match[0][0])));

                if ($this->hasSignatureEvidence($suffix)) {
                    return trim(substr($normalized, 0, $offset));
                }
            }
        }

        return trim($markdown);
    }

    protected function hasSignatureEvidence($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return false;
        }

        $lines = preg_split('/\r?\n/', $text);
        $nameFound = false;
        $evidenceCount = 0;

        foreach ($lines as $line) {
            $candidate = trim((string) $line);
            if ($candidate === '' || preg_match('/^(?:--|[-_]{5,})$/', $candidate)) {
                continue;
            }

            if (preg_match('/^[A-Z][\p{L}\'\-]+(?:\s+[A-Z][\p{L}\'\-]+){1,3}$/u', $candidate)) {
                $nameFound = true;
            }

            if (preg_match('/https?:\/\/|www\./i', $candidate)) {
                $evidenceCount++;
            }
            if (preg_match('/\b(?:tel:|mob:|phone:|mobile:|\+\d{2,})\b|@[A-Z0-9._%+-]+\.[A-Z]{2,}|\b(LinkedIn|Facebook|Instagram|Twitter|YouTube|X)\b/i', $candidate)) {
                $evidenceCount++;
            }
            if (preg_match('/\b(ltd\.?|limited|inc\.?|llc|administrator|officer|manager|director|department|team)\b/i', $candidate)) {
                $evidenceCount++;
            }
        }

        if ($nameFound && $evidenceCount >= 2) {
            return true;
        }

        $compact = preg_replace('/\s+/', ' ', $text);
        return preg_match('/^[A-Z][\p{L}\'\-]+(?:\s+[A-Z][\p{L}\'\-]+){1,3}\s+(?:LinkedIn|Facebook|Instagram|Twitter|YouTube|X|https?:\/\/|www\.|@[-A-Z0-9._%+]+\.[A-Z]{2,}|\+\d)/iu', $compact) && preg_match('/(?:https?:\/\/|www\.|\+\d{2,}|LinkedIn|Facebook|Instagram|Twitter|YouTube|X|@[-A-Z0-9._%+]+\.[A-Z]{2,})/i', $compact);
    }

    protected function looksLikeSignatureLine($line)
    {
        return (bool) preg_match(
            '/\b(LinkedIn|Facebook|Instagram|Twitter|YouTube|X|tel:|mob:|phone:|mobile:|ltd\.?|limited|inc\.?|llc|administrator|officer|manager|director|department|team)\b|\[[^\]]+\]\((?:https?:\/\/|<https?:\/\/)|@[A-Z0-9._%+-]+\.[A-Z]{2,}|(?:^|[| ])(?:T|M)\d|cid:/i',
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

        if (preg_match('/https?:\/\/|www\./i', $text) && preg_match('/\b(?:LinkedIn|Facebook|Instagram|Twitter|YouTube|X|tel:|mob:|phone:|mobile:|@[A-Z0-9._%+-]+\.[A-Z]{2,})\b/i', $text)) {
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
