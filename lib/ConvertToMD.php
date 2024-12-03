<?php

namespace Mail2Deck;

use League\HTMLToMarkdown\HtmlConverter;

class ConvertToMD {
    protected $html;
    protected $converter;

    public function __construct($html) {
        $this->converter = new HtmlConverter([
            'strip_tags' => true,
            'remove_nodes' => 'title'
        ]);
        $this->html = $html;
    }

    public function execute()
    {
        return $this->converter->convert($this->html);
    }
}
