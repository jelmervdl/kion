<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    private $parser;

    public function __construct()
    {
        $this->parser = new \Parsedown();
    }

    public function getFilters(): array
    {
        return array(
            new TwigFilter('markdown', array($this, 'markdownFilter'), ['is_safe' => ['html']]),
        );
    }

    public function markdownFilter(string $text): string
    {
        return $this->parser->text($text);
    }
}