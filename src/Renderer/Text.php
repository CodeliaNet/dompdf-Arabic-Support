<?php
/**
 * @package dompdf
 * @link    http://dompdf.github.com/
 * @author  Benj Carson <benjcarson@digitaljunkies.ca>
 * @author  Helmut Tischer <htischer@weihenstephan.org>
 * @author  Fabien Ménager <fabien.menager@gmail.com>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
namespace Dompdf\Renderer;

use Dompdf\Adapter\CPDF;
use Dompdf\Frame;


/**
 * Renders text frames
 *
 * @package dompdf
 */
class Text extends AbstractRenderer
{
    /** Thickness of underline. Screen: 0.08, print: better less, e.g. 0.04 */
    const DECO_THICKNESS = 0.02;

    //Tweaking if $base and $descent are not accurate.
    //Check method_exists( $this->_canvas, "get_cpdf" )
    //- For cpdf these can and must stay 0, because font metrics are used directly.
    //- For other renderers, if different values are wanted, separate the parameter sets.
    //  But $size and $size-$height seem to be accurate enough

    /** Relative to bottom of text, as fraction of height */
    const UNDERLINE_OFFSET = 0.0;

    /** Relative to top of text */
    const OVERLINE_OFFSET = 0.0;

    /** Relative to centre of text. */
    const LINETHROUGH_OFFSET = 0.0;

    /** How far to extend lines past either end, in pt */
    const DECO_EXTENSION = 0.0;

    /**
     * @param \Dompdf\FrameDecorator\Text $frame
     */
    function render(Frame $frame)
    {
        $style = $frame->get_style();
        $text = $frame->get_text();

        if (trim($text) === "") {
            return;
        }

        $this->_set_opacity($frame->get_opacity($style->opacity));

        list($x, $y) = $frame->get_position();
        $cb = $frame->get_containing_block();

        $ml = $style->margin_left;
        $pl = $style->padding_left;
        $bl = $style->border_left_width;
        $x += (float) $style->length_in_pt([$ml, $pl, $bl], $cb["w"]);

        $font = $style->font_family;
        $size = $style->font_size;
        $frame_font_size = $frame->get_dompdf()->getFontMetrics()->getFontHeight($font, $size);
        $word_spacing = $frame->get_text_spacing() + $style->word_spacing;
        $letter_spacing = $style->letter_spacing;
        $width = (float) $style->width;

        /*$text = str_replace(
          array("{PAGE_NUM}"),
          array($this->_canvas->get_page_number()),
          $text
        );*/

         $Arabic = new I18N_Arabic_Glyphs('Glyphs'); 
         $text = $Arabic->utf8Glyphs($text, 150); 

        $this->_canvas->text($x, $y, $text,
            $font, $size,
            $style->color, $word_spacing, $letter_spacing);

        $line = $frame->get_containing_line();

        // FIXME Instead of using the tallest frame to position,
        // the decoration, the text should be well placed
        if (false && $line->tallest_frame) {
            $base_frame = $line->tallest_frame;
            $style = $base_frame->get_style();
            $size = $style->font_size;
        }

        $line_thickness = $size * self::DECO_THICKNESS;
        $underline_offset = $size * self::UNDERLINE_OFFSET;
        $overline_offset = $size * self::OVERLINE_OFFSET;
        $linethrough_offset = $size * self::LINETHROUGH_OFFSET;
        $underline_position = -0.08;

        if ($this->_canvas instanceof CPDF) {
            $cpdf_font = $this->_canvas->get_cpdf()->fonts[$style->font_family];

            if (isset($cpdf_font["UnderlinePosition"])) {
                $underline_position = $cpdf_font["UnderlinePosition"] / 1000;
            }

            if (isset($cpdf_font["UnderlineThickness"])) {
                $line_thickness = $size * ($cpdf_font["UnderlineThickness"] / 1000);
            }
        }

        $descent = $size * $underline_position;
        $base = $frame_font_size;

        // Handle text decoration:
        // http://www.w3.org/TR/CSS21/text.html#propdef-text-decoration

        // Draw all applicable text-decorations.  Start with the root and work our way down.
        $p = $frame;
        $stack = [];
        while ($p = $p->get_parent()) {
            $stack[] = $p;
        }

        while (isset($stack[0])) {
            $f = array_pop($stack);

            if (($text_deco = $f->get_style()->text_decoration) === "none") {
                continue;
            }

            $deco_y = $y; //$line->y;
            $color = $f->get_style()->color;

            switch ($text_deco) {
                default:
                    continue 2;

                case "underline":
                    $deco_y += $base - $descent + $underline_offset + $line_thickness / 2;
                    break;

                case "overline":
                    $deco_y += $overline_offset + $line_thickness / 2;
                    break;

                case "line-through":
                    $deco_y += $base * 0.7 + $linethrough_offset;
                    break;
            }

            $dx = 0;
            $x1 = $x - self::DECO_EXTENSION;
            $x2 = $x + $width + $dx + self::DECO_EXTENSION;
            $this->_canvas->line($x1, $deco_y, $x2, $deco_y, $color, $line_thickness);
        }

        if ($this->_dompdf->getOptions()->getDebugLayout() && $this->_dompdf->getOptions()->getDebugLayoutLines()) {
            $text_width = $this->_dompdf->getFontMetrics()->getTextWidth($text, $font, $size, $word_spacing, $letter_spacing);
            $this->_debug_layout([$x, $y, $text_width, $frame_font_size], "orange", [0.5, 0.5]);
        }
    }
}

class I18N_Arabic_Glyphs
{
    private $_glyphs   = null;
    private $_hex      = null;
    private $_prevLink = null;
    private $_nextLink = null;
    private $_vowel    = null;

    /**
     * Loads initialize values
     *
     * @ignore
     */         
    public function __construct()
    {
        $this->_prevLink = '،؟؛ـئبتثجحخسشصضطظعغفقكلمنهي';
        $this->_nextLink = 'ـآأؤإائبةتثجحخدذرزسشصضطظعغفقكلمنهوىي';
        $this->_vowel    = 'ًٌٍَُِّْ';

        /*
         $this->_glyphs['ً']  = array('FE70','FE71');
         $this->_glyphs['ٌ']  = array('FE72','FE72');
         $this->_glyphs['ٍ']  = array('FE74','FE74');
         $this->_glyphs['َ']  = array('FE76','FE77');
         $this->_glyphs['ُ']  = array('FE78','FE79');
         $this->_glyphs['ِ']  = array('FE7A','FE7B');
         $this->_glyphs['ّ']  = array('FE7C','FE7D');
         $this->_glyphs['ْ']  = array('FE7E','FE7E');
         */
         
        $this->_glyphs = 'ًٌٍَُِّْٰ';
        $this->_hex    = '064B064B064B064B064C064C064C064C064D064D064D064D064E064E';
        $this->_hex   .= '064E064E064F064F064F064F06500650065006500651065106510651';
        $this->_hex   .= '06520652065206520670067006700670';

        $this->_glyphs .= 'ءآأؤإئاب';
        $this->_hex    .= 'FE80FE80FE80FE80FE81FE82FE81FE82FE83FE84FE83FE84FE85FE86';
        $this->_hex    .= 'FE85FE86FE87FE88FE87FE88FE89FE8AFE8BFE8CFE8DFE8EFE8DFE8E';
        $this->_hex    .= 'FE8FFE90FE91FE92';

        $this->_glyphs .= 'ةتثجحخدذ';
        $this->_hex    .= 'FE93FE94FE93FE94FE95FE96FE97FE98FE99FE9AFE9BFE9CFE9DFE9E';
        $this->_hex    .= 'FE9FFEA0FEA1FEA2FEA3FEA4FEA5FEA6FEA7FEA8FEA9FEAAFEA9FEAA';
        $this->_hex    .= 'FEABFEACFEABFEAC';

        $this->_glyphs .= 'رزسشصضطظ';
        $this->_hex    .= 'FEADFEAEFEADFEAEFEAFFEB0FEAFFEB0FEB1FEB2FEB3FEB4FEB5FEB6';
        $this->_hex    .= 'FEB7FEB8FEB9FEBAFEBBFEBCFEBDFEBEFEBFFEC0FEC1FEC2FEC3FEC4';
        $this->_hex    .= 'FEC5FEC6FEC7FEC8';

        $this->_glyphs .= 'عغفقكلمن';
        $this->_hex    .= 'FEC9FECAFECBFECCFECDFECEFECFFED0FED1FED2FED3FED4FED5FED6';
        $this->_hex    .= 'FED7FED8FED9FEDAFEDBFEDCFEDDFEDEFEDFFEE0FEE1FEE2FEE3FEE4';
        $this->_hex    .= 'FEE5FEE6FEE7FEE8';

        $this->_glyphs .= 'هوىيـ،؟؛';
        $this->_hex    .= 'FEE9FEEAFEEBFEECFEEDFEEEFEEDFEEEFEEFFEF0FEEFFEF0FEF1FEF2';
        $this->_hex    .= 'FEF3FEF40640064006400640060C060C060C060C061F061F061F061F';
        $this->_hex    .= '061B061B061B061B';

        // Support the extra 4 Persian letters (p), (ch), (zh) and (g)
        // This needs value in getGlyphs function to be 52 instead of 48
        // $this->_glyphs .= chr(129).chr(141).chr(142).chr(144);
        // $this->_hex    .= 'FB56FB57FB58FB59FB7AFB7BFB7CFB7DFB8AFB8BFB8AFB8BFB92';
        // $this->_hex    .= 'FB93FB94FB95';
        //
        // $this->_prevLink .= chr(129).chr(141).chr(142).chr(144);
        // $this->_nextLink .= chr(129).chr(141).chr(142).chr(144);
        //
        // Example:     $text = 'نمونة قلم: لاگچ ژافپ';
        // Email Yossi Beck <yosbeck@gmail.com> ask him to save that example
        // string using ANSI encoding in Notepad
        $this->_glyphs .= '';
        $this->_hex    .= '';
        
        $this->_glyphs .= 'لآلألإلا';
        $this->_hex    .= 'FEF5FEF6FEF5FEF6FEF7FEF8FEF7FEF8FEF9FEFAFEF9FEFAFEFBFEFC';
        $this->_hex    .= 'FEFBFEFC';
    }
    
    /**
     * Get glyphs
     * 
     * @param string  $char Char
     * @param integer $type Type
     * 
     * @return string
     */                                  
    protected function getGlyphs($char, $type)
    {

        $pos = mb_strpos($this->_glyphs, $char);
        
        if ($pos > 49) {
            $pos = ($pos-49)/2 + 49;
        }
        
        $pos = $pos*16 + $type*4;
        
        return substr($this->_hex, $pos, 4);
    }
    
    /**
     * Convert Arabic Windows-1256 charset string into glyph joining in UTF-8 
     * hexadecimals stream
     *      
     * @param string $str Arabic string in Windows-1256 charset
     *      
     * @return string Arabic glyph joining in UTF-8 hexadecimals stream
     * @author Khaled Al-Sham'aa <khaled@ar-php.org>
     */
    protected function preConvert($str)
    {
        $crntChar = null;
        $prevChar = null;
        $nextChar = null;
        $output   = '';
        
        $_temp = mb_strlen($str);
        $chars = array();
        for ($i = 0; $i < $_temp; $i++) {
            $chars[] = mb_substr($str, $i, 1);
        }

        $max = count($chars);

        for ($i = $max - 1; $i >= 0; $i--) {
            $crntChar = $chars[$i];
            $prevChar = ' ';
            
            if ($i > 0) {
                $prevChar = $chars[$i - 1];
            }
            
            if ($prevChar && mb_strpos($this->_vowel, $prevChar) !== false) {
                $prevChar = $chars[$i - 2];
                if ($prevChar && mb_strpos($this->_vowel, $prevChar) !== false) {
                    $prevChar = $chars[$i - 3];
                }
            }
            
            $Reversed    = false;
            $flip_arr    = ')]>}';
            $ReversedChr = '([<{';
            
            if ($crntChar && mb_strpos($flip_arr, $crntChar) !== false) {
                $crntChar = $ReversedChr[mb_strpos($flip_arr, $crntChar)];
                $Reversed = true;
            } else {
                $Reversed = false;
            }
            
            if ($crntChar && !$Reversed 
                && (mb_strpos($ReversedChr, $crntChar) !== false)
            ) {
                $crntChar = $flip_arr[mb_strpos($ReversedChr, $crntChar)];
            }
            
            if (ord($crntChar) < 128) {
                $output  .= $crntChar;
                $nextChar = $crntChar;
                continue;
            }
            
            if ($crntChar == 'ل' && isset($chars[$i + 1]) 
                && (mb_strpos('آأإا', $chars[$i + 1]) !== false)
            ) {
                continue;
            }
            
            if ($crntChar && mb_strpos($this->_vowel, $crntChar) !== false) {
                if (isset($chars[$i + 1]) 
                    && (mb_strpos($this->_nextLink, $chars[$i + 1]) !== false) 
                    && (mb_strpos($this->_prevLink, $prevChar) !== false)
                ) {
                    $output .= '&#x' . $this->getGlyphs($crntChar, 1) . ';';
                } else {
                    $output .= '&#x' . $this->getGlyphs($crntChar, 0) . ';';
                }
                continue;
            }
            
            $form = 0;
            
            if (($prevChar == 'لا' || $prevChar == 'لآ' || $prevChar == 'لأ' 
                || $prevChar == 'لإ' || $prevChar == 'ل') 
                && (mb_strpos('آأإا', $crntChar) !== false)
            ) {
                if (isset($chars[$i - 2]) && mb_strpos($this->_prevLink, $chars[$i - 2]) !== false) {
                    $form++;
                }
                
                if (isset($chars[$i - 2]) && mb_strpos($this->_vowel, $chars[$i - 1])) {
                    $output .= '&#x';
                    $output .= $this->getGlyphs($crntChar, $form).';';
                } else {
                    $output .= '&#x';
                    $output .= $this->getGlyphs($prevChar.$crntChar, $form).';';
                }
                $nextChar = $prevChar;
                continue;
            }
            
            if ($prevChar && mb_strpos($this->_prevLink, $prevChar) !== false) {
                $form++;
            }
            
            if ($nextChar && mb_strpos($this->_nextLink, $nextChar) !== false) {
                $form += 2;
            }
            
            $output  .= '&#x' . $this->getGlyphs($crntChar, $form) . ';';
            $nextChar = $crntChar;
        }
        
        // from Arabic Presentation Forms-B, Range: FE70-FEFF, 
        // file "UFE70.pdf" (in reversed order)
        // into Arabic Presentation Forms-A, Range: FB50-FDFF, file "UFB50.pdf"
        // Example: $output = str_replace('&#xFEA0;&#xFEDF;', '&#xFCC9;', $output);
        // Lam Jeem

        $output = $this->decodeEntities($output, $exclude = array('&'));
        return $output;
    }
    
    /**
     * Regression analysis calculate roughly the max number of character fit in 
     * one A4 page line for a given font size.
     *      
     * @param integer $font Font size
     *      
     * @return integer Maximum number of characters per line
     * @author Khaled Al-Sham'aa <khaled@ar-php.org>
     */
    public function a4MaxChars($font)
    {
        $x = 381.6 - 31.57 * $font + 1.182 * pow($font, 2) - 0.02052 * 
             pow($font, 3) + 0.0001342 * pow($font, 4);
        return floor($x - 2);
    }
    
    /**
     * Calculate the lines number of given Arabic text and font size that will 
     * fit in A4 page size
     *      
     * @param string  $str  Arabic string you would like to split it into lines
     * @param integer $font Font size
     *                    
     * @return integer Number of lines for a given Arabic string in A4 page size
     * @author Khaled Al-Sham'aa <khaled@ar-php.org>
     */
    public function a4Lines($str, $font)
    {
        $str = str_replace(array("\r\n", "\n", "\r"), "\n", $str);
        
        $lines     = 0;
        $chars     = 0;
        $words     = explode(' ', $str);
        $w_count   = count($words);
        $max_chars = $this->a4MaxChars($font);
        
        for ($i = 0; $i < $w_count; $i++) {
            $w_len = mb_strlen($words[$i]) + 1;
            
            if ($chars + $w_len < $max_chars) {
                if (mb_strpos($words[$i], "\n") !== false) {
                    $words_nl = explode("\n", $words[$i]);
                    
                    $nl_num = count($words_nl) - 1;
                    for ($j = 1; $j < $nl_num; $j++) {
                        $lines++;
                    }
                    
                    $chars = mb_strlen($words_nl[$nl_num]) + 1;
                } else {
                    $chars += $w_len;
                }
            } else {
                $lines++;
                $chars = $w_len;
            }
        }
        $lines++;
        
        return $lines;
    }
    
    /**
     * Convert Arabic Windows-1256 charset string into glyph joining in UTF-8 
     * hexadecimals stream (take care of whole the document including English 
     * sections as well as numbers and arcs etc...)
     *                    
     * @param string  $str       Arabic string in Windows-1256 charset
     * @param integer $max_chars Max number of chars you can fit in one line
     * @param boolean $hindo     If true use Hindo digits else use Arabic digits
     *                    
     * @return string Arabic glyph joining in UTF-8 hexadecimals stream (take
     *                care of whole document including English sections as well
     *                as numbers and arcs etc...)
     * @author Khaled Al-Sham'aa <khaled@ar-php.org>
     */
    public function utf8Glyphs($str, $max_chars = 150, $hindo = true)
    {
        $str = str_replace(array("\r\n", "\n", "\r"), " \n ", $str);
        $str = str_replace("\t", "        ", $str);
        
        $lines   = array();
        $words   = explode(' ', $str);
        $w_count = count($words);
        $c_chars = 0;
        $c_words = array();
        
        $english  = array();
        $en_index = -1;
        
        $en_words = array();
        $en_stack = array();

        for ($i = 0; $i < $w_count; $i++) {
            $pattern  = '/^(\n?)';
            $pattern .= '[a-z\d\\/\@\#\$\%\^\&\*\(\)\_\~\"\'\[\]\{\}\;\,\|\-\.\:!]*';
            $pattern .= '([\.\:\+\=\-\!،؟]?)$/i';
            
            if (preg_match($pattern, $words[$i], $matches)) {
                if ($matches[1]) {
                    $words[$i] = mb_substr($words[$i], 1).$matches[1];
                }
                if ($matches[2]) {
                    $words[$i] = $matches[2].mb_substr($words[$i], 0, -1);
                }
                $words[$i] = strrev($words[$i]);
                array_push($english, $words[$i]);
                if ($en_index == -1) {
                    $en_index = $i;
                }
                $en_words[] = true;
            } elseif ($en_index != -1) {
                $en_count = count($english);
                
                for ($j = 0; $j < $en_count; $j++) {
                    $words[$en_index + $j] = $english[$en_count - 1 - $j];
                }
                
                $en_index = -1;
                $english  = array();
                
                $en_words[] = false;
            } else {
                $en_words[] = false;
            }
        }

        if ($en_index != -1) {
            $en_count = count($english);
            
            for ($j = 0; $j < $en_count; $j++) {
                $words[$en_index + $j] = $english[$en_count - 1 - $j];
            }
        }

        // need more work to fix lines starts by English words
        if (isset($en_start)) {
            $last = true;
            $from = 0;
            
            foreach ($en_words as $key => $value) {
                if ($last !== $value) {
                    $to = $key - 1;
                    array_push($en_stack, array($from, $to));
                    $from = $key;
                }
                $last = $value;
            }
            
            array_push($en_stack, array($from, $key));
            
            $new_words = array();
            
            while (list($from, $to) = array_pop($en_stack)) {
                for ($i = $from; $i <= $to; $i++) {
                    $new_words[] = $words[$i];
                }
            }
            
            $words = $new_words;
        }

        for ($i = 0; $i < $w_count; $i++) {
            $w_len = mb_strlen($words[$i]) + 1;
            
            if ($c_chars + $w_len < $max_chars) {
                if (mb_strpos($words[$i], "\n") !== false) {
                    $words_nl = explode("\n", $words[$i]);
                    
                    array_push($c_words, $words_nl[0]);
                    array_push($lines, implode(' ', $c_words));
                    
                    $nl_num = count($words_nl) - 1;
                    for ($j = 1; $j < $nl_num; $j++) {
                        array_push($lines, $words_nl[$j]);
                    }
                    
                    $c_words = array($words_nl[$nl_num]);
                    $c_chars = mb_strlen($words_nl[$nl_num]) + 1;
                } else {
                    array_push($c_words, $words[$i]);
                    $c_chars += $w_len;
                }
            } else {
                array_push($lines, implode(' ', $c_words));
                $c_words = array($words[$i]);
                $c_chars = $w_len;
            }
        }
        array_push($lines, implode(' ', $c_words));
        
        $maxLine = count($lines);
        $output  = '';
        
        for ($j = $maxLine - 1; $j >= 0; $j--) {
            $output .= $lines[$j] . "\n";
        }
        
        $output = rtrim($output);
        
        $output = $this->preConvert($output);
        if ($hindo) {
            $nums   = array(
                '0', '1', '2', '3', '4', 
                '5', '6', '7', '8', '9'
            );
            $arNums = array(
                '٠', '١', '٢', '٣', '٤',
                '٥', '٦', '٧', '٨', '٩'
            );
            
            foreach ($nums as $k => $v) {
                $p_nums[$k] = '/'.$v.'/ui';
            }
            $output = preg_replace($p_nums, $arNums, $output);
            
            foreach ($arNums as $k => $v) {
                $p_arNums[$k] = '/([a-z-\d]+)'.$v.'/ui';
            }
            foreach ($nums as $k => $v) {
                $r_nums[$k] = '${1}'.$v;
            }
            $output = preg_replace($p_arNums, $r_nums, $output);
            
            foreach ($arNums as $k => $v) {
                $p_arNums[$k] = '/'.$v.'([a-z-\d]+)/ui';
            }
            foreach ($nums as $k => $v) {
                $r_nums[$k] = $v.'${1}';
            }
            $output = preg_replace($p_arNums, $r_nums, $output);
        }

        return $output;
    }
    
    /**
     * Decode all HTML entities (including numerical ones) to regular UTF-8 bytes. 
     * Double-escaped entities will only be decoded once 
     * ("&amp;lt;" becomes "&lt;", not "<").
     *                   
     * @param string $text    The text to decode entities in.
     * @param array  $exclude An array of characters which should not be decoded.
     *                        For example, array('<', '&', '"'). This affects
     *                        both named and numerical entities.
     *                        
     * @return string           
     */
    protected function decodeEntities($text, $exclude = array())
    {
        static $table;
        
        // We store named entities in a table for quick processing.
        if (!isset($table)) {
            // Get all named HTML entities.
            $table = array_flip(get_html_translation_table(HTML_ENTITIES));
            
            // PHP gives us ISO-8859-1 data, we need UTF-8.
            $table = array_map('utf8_encode', $table);
            
            // Add apostrophe (XML)
            $table['&apos;'] = "'";
        }
        $newtable = array_diff($table, $exclude);
        
        // Use a regexp to select all entities in one pass, to avoid decoding 
        // double-escaped entities twice.
        //return preg_replace('/&(#x?)?([A-Za-z0-9]+);/e', 
        //                    '$this->decodeEntities2("$1", "$2", "$0", $newtable, 
        //                                             $exclude)', $text);

        $pieces = explode('&', $text);
        $text   = array_shift($pieces);
        foreach ($pieces as $piece) {
            if ($piece[0] == '#') {
                if ($piece[1] == 'x') {
                    $one = '#x';
                } else {
                    $one = '#';
                }
            } else {
                $one = '';
            }
            $end   = mb_strpos($piece, ';');
            $start = mb_strlen($one);
            
            $two   = mb_substr($piece, $start, $end - $start);
            $zero  = '&'.$one.$two.';';
            $text .= $this->decodeEntities2($one, $two, $zero, $newtable, $exclude).
                     mb_substr($piece, $end+1);
        }
        return $text;
    }
    
    /**
     * Helper function for decodeEntities
     * 
     * @param string $prefix    Prefix      
     * @param string $codepoint Codepoint         
     * @param string $original  Original        
     * @param array  &$table    Store named entities in a table      
     * @param array  &$exclude  An array of characters which should not be decoded
     * 
     * @return string                  
     */
    protected function decodeEntities2(
        $prefix, $codepoint, $original, &$table, &$exclude
    ) {
        // Named entity
        if (!$prefix) {
            if (isset($table[$original])) {
                return $table[$original];
            } else {
                return $original;
            }
        }
        
        // Hexadecimal numerical entity
        if ($prefix == '#x') {
            $codepoint = base_convert($codepoint, 16, 10);
        }
        
        // Encode codepoint as UTF-8 bytes
        if ($codepoint < 0x80) {
            $str = chr($codepoint);
        } elseif ($codepoint < 0x800) {
            $str = chr(0xC0 | ($codepoint >> 6)) . 
                   chr(0x80 | ($codepoint & 0x3F));
        } elseif ($codepoint < 0x10000) {
            $str = chr(0xE0 | ($codepoint >> 12)) . 
                   chr(0x80 | (($codepoint >> 6) & 0x3F)) . 
                   chr(0x80 | ($codepoint & 0x3F));
        } elseif ($codepoint < 0x200000) {
            $str = chr(0xF0 | ($codepoint >> 18)) . 
                   chr(0x80 | (($codepoint >> 12) & 0x3F)) . 
                   chr(0x80 | (($codepoint >> 6) & 0x3F)) . 
                   chr(0x80 | ($codepoint & 0x3F));
        }
        
        // Check for excluded characters
        if (in_array($str, $exclude)) {
            return $original;
        } else {
            return $str;
        }
    }
}

