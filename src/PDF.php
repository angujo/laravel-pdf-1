<?php

namespace Angujo\LaravelPDF;

use Config, Exception, File, View;
use Mpdf\Mpdf;
use Mpdf\Utils\UtfString;

//use font_data

Class PDF extends Mpdf
{

    protected $config = [];

    public function __construct($configs = [])
    {

        $config = Config::get('pdf');

        if (!$config) {
            $config = include(__DIR__ . '/../config/pdf.php');
        }

        $this->config = array_merge($config, $configs);


        if (Config::has('pdf.temp_dir') && $this->getConfig('temp_dir')) {
            define('_MPDF_TEMP_PATH', $this->getConfig('temp_dir'));
            if (!file_exists(_MPDF_TEMP_PATH)) {
                if (!mkdir(_MPDF_TEMP_PATH, 0755, true) && !is_dir(_MPDF_TEMP_PATH)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', _MPDF_TEMP_PATH));
                }
            }
        }
        if (Config::has('pdf.temp_fonts') && $this->getConfig('temp_fonts')) {
            define('_MPDF_TTFONTDATAPATH', $this->getConfig('temp_fonts'));
            if (!file_exists(_MPDF_TTFONTDATAPATH)) {
                if (!mkdir(_MPDF_TTFONTDATAPATH, 0755, true) && !is_dir(_MPDF_TTFONTDATAPATH)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', _MPDF_TTFONTDATAPATH));
                }
            }
        }
        if (Config::has('pdf.custom_font_path') && Config::has('pdf.custom_font_data')) {
            define('_MPDF_SYSTEM_TTFONTS_CONFIG', __DIR__ . '/../mpdf_ttfonts_config.php');
        }

        $defConfig = [
            'mode' => $this->getConfig('mode'),              // mode - default ''
            'format' => $this->getConfig('format'),            // format - A4, for example, default ''
            'default_font_size' => $this->getConfig('default_font_size'), // font size - default 0
            'default_font' => $this->getConfig('default_font'),      // default font family
            'margin_left' => $this->getConfig('margin_left'),       // margin_left
            'margin_right' => $this->getConfig('margin_right'),      // margin right
            'margin_top' => $this->getConfig('margin_top'),        // margin top
            'margin_bottom' => $this->getConfig('margin_bottom'),     // margin bottom
            'margin_header' => $this->getConfig('margin_header'),     // margin header
            'margin_footer' => $this->getConfig('margin_footer'),     // margin footer
            'orientation' => $this->getConfig('orientation'),       // L - landscape, P - portrait
        ];
        if (defined('_MPDF_TEMP_PATH') && file_exists(_MPDF_TEMP_PATH)) {
            $defConfig['tempDir'] = _MPDF_TEMP_PATH;
        }
        parent::__construct($defConfig);

        if (Config::has('pdf.debug_imgs') && $this->getConfig('pdf.debug_imgs')){
            $this->showImageErrors=true;
            $this->debug=true;
        }

        $font_data = include(__DIR__ . '/fontdata.php');
        if (is_array($font_data)) {
            $this->fontdata = array_merge($this->fontdata, $font_data);

            foreach ($font_data AS $f => $fs) {
                if (isset($fs['R']) && $fs['R']) {
                    $this->available_unifonts[] = $f;
                }
                if (isset($fs['B']) && $fs['B']) {
                    $this->available_unifonts[] = $f . 'B';
                }
                if (isset($fs['I']) && $fs['I']) {
                    $this->available_unifonts[] = $f . 'I';
                }
                if (isset($fs['BI']) && $fs['BI']) {
                    $this->available_unifonts[] = $f . 'BI';
                }
            }

            $this->default_available_fonts = $this->available_unifonts;
        }

        $this->SetTitle($this->getConfig('title'));
        $this->SetAuthor($this->getConfig('author'));
        $this->SetWatermarkText($this->getConfig('watermark'));
        $this->SetDisplayMode($this->getConfig('display_mode'));
        $this->SetDirectionality($this->getConfig('dir') ? $this->getConfig('dir') : $this->getConfig('direction'));
        $this->showWatermarkText = $this->getConfig('show_watermark');
        $this->watermark_font = $this->getConfig('watermark_font');
        $this->watermarkTextAlpha = $this->getConfig('watermark_text_alpha');

    }

    public function Make()
    {
        return $this;
    }

    public function SetDirection($dir)
    {
        $this->SetDirectionality($dir);

        return $this;
    }

    public function loadHTML($html)
    {

        $wm = UtfString::strcode2utf($html);

        $this->WriteHTML($wm);
    }

    public function loadFile($file, $config = [])
    {
        $this->WriteHTML(File::get($file));
    }

    public function loadView($view, $data = [], $mergeData = [])
    {
        $this->WriteHTML(View::make($view, $data, $mergeData)->render());
    }

    /*
     * $fontdata = [
     *       'sourcesanspro' => [
     *           'R' => 'SourceSansPro-Regular.ttf',
     *           'B' => 'SourceSansPro-Bold.ttf',
     *       ],
     *   ];
     */
    public function addCustomFont($fonts_list, $is_unicode = false)
    {

        if (empty($fonts_list) || !isset($fonts_list)) {
            throw new Exception('Please add font data in EmbedFont() function.');
        }

        $custom_font_path = $this->getConfig('custom_font_path');
        if (!$custom_font_path) {
            throw new Exception('custom_font_path not set in "config/pdf.php" file.');
        } else {
            $custom_font_path = rtrim($custom_font_path, '/');
        }

        foreach ($fonts_list as $f => $fs) {
            if (is_array($fs)) {

                foreach (['R', 'B', 'I', 'BI'] as $style) {
                    if (isset($fs[$style]) && $fs[$style]) {
                        $font = $fs[$style];
                        $font_file = $custom_font_path . '/' . $font;

                        if (!file_exists(base_path('vendor/mpdf/mpdf/ttfonts/' . $font))) {
                            if (file_exists($font_file)) {
                                File::copy($font_file, base_path('vendor/mpdf/mpdf/ttfonts/' . $font));
                            } else {
                                throw new Exception('Your font file "' . $font_file . '" not exist.');
                            }
                        }
                    }
                }
            }
        }

        $this->addFontData($fonts_list, $is_unicode);
    }

    protected function addFontData($fonts, $unicode = false)
    {

        $font_data = include(__DIR__ . '/fontdata.php');

        foreach ($fonts as $key => $val) {
            $key = strtolower($key);
            if (is_array($val)) {

                foreach (['R', 'B', 'I', 'BI'] as $style) {
                    if (isset($val[$style]) && $val[$style]) {
                        $font = $val[$style];
                        $this->available_unifonts[] = $key . trim($style, 'R');
                    }
                }
                if ($unicode) {
                    $val['useKashida'] = 75;
                    $val['useOTL'] = 0xFF;
                }
                $font_data[$key] = $val;

                $this->fontdata[$key] = $val;
            }
        }

        $this->default_available_fonts = $this->available_unifonts;

        $file = __DIR__ . '/fontdata.php';

        $output = "<?php return " .
            $this->array2str($font_data) . " ;";

        $handle = fopen($file, 'w');

        fwrite($handle, $output);

        fclose($handle);
    }

    protected function array2str($arr)
    {
        $retStr = '';
        if (is_array($arr)) {
            $retStr .= "[ \r";
            foreach ($arr as $key => $val) {
                if (is_array($val)) {
                    $retStr .= "\t'" . $key . "' => " . $this->array2str($val) . ",\r";
                } else {
                    if (is_string($val)) {
                        $retStr .= "\t'" . $key . "' => '" . $val . "',\r";
                    } else {
                        $retStr .= "\t'" . $key . "' => " . ($key == 'useOTL' ? '0xFF' : $val) . ",\r";
                    }
                }
            }
            $retStr .= " ]";
        }

        return $retStr;
    }

    protected function getConfig($key)
    {
        if (isset($this->config[$key])) {
            return $this->config[$key];
        } else {
            return Config::get('pdf.' . $key);
        }
    }

    public function Embed($name, $dest = 'S')
    {
        return $this->Output($name, $dest);
    }

    public function Save($filename)
    {
        return $this->Output($filename, 'F');
    }

    public function Download($filename = 'document.pdf')
    {
        return $this->Output($filename, 'D');
    }

    public function Stream($filename = 'document.pdf')
    {
        return $this->Output($filename, 'I');
    }
}
