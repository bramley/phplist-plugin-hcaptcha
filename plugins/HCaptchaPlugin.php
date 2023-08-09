<?php
/**
 * HCaptchaPlugin for phplist.
 *
 * This file is a part of HCaptchaPlugin.
 *
 * @author    Duncan Cameron
 * @copyright 2021 Duncan Cameron
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 *
 * @see       https://docs.hcaptcha.com/
 */
use phpList\plugin\Common\FrontendTranslator;
use phpList\plugin\Common\Logger;
use phpList\plugin\Common\StringStream;

/**
 * This class registers the plugin with phplist and hooks into the display and validation
 * of subscribe pages.
 */
class HCaptchaPlugin extends phplistPlugin
{
    /** @var string the name of the version file */
    const VERSION_FILE = 'version.txt';

    /** @var string the site key */
    private $siteKey;

    /** @var string the secret key */
    private $secretKey;

    /** @var bool whether the site and secret keys have been entered */
    private $keysEntered;

    /*
     *  Inherited from phplistPlugin
     */
    public $name = 'hCaptcha Plugin';
    public $description = 'Adds an hCaptcha field to subscribe forms';
    public $documentationUrl = 'https://resources.phplist.com/plugin/hcaptcha';
    public $authors = 'Duncan Cameron';
    public $coderoot;

    /**
     * Derive the Google language code from the subscribe page language file name.
     *
     * @see https://developers.google.com/recaptcha/docs/language
     *
     * @param string $languageFile the language file name
     *
     * @return string the language code, or an empty string when it cannot
     *                be derived
     */
    private function languageCode($languageFile)
    {
        $fileToCode = array(
            'afrikaans.inc' => 'af',
            'arabic.inc' => 'ar',
            'belgianflemish.inc' => '',
            'bosnian.inc' => 'bs',
            'bulgarian.inc' => 'bg',
            'catalan.inc' => 'ca',
            'croatian.inc' => 'hr',
            'czech.inc' => 'cs',
            'danish.inc' => 'da',
            'dutch.inc' => 'nl',
            'english-gaelic.inc' => 'en-GB',
            'english.inc' => 'en-GB',
            'english-usa.inc' => 'en',
            'estonian.inc' => 'et',
            'finnish.inc' => 'fi',
            'french.inc' => 'fr',
            'german.inc' => 'de',
            'greek.inc' => 'el',
            'hebrew.inc' => 'iw',
            'hungarian.inc' => 'hu',
            'indonesian.inc' => 'id',
            'italian.inc' => 'it',
            'japanese.inc' => 'ja',
            'latinamerican.inc' => 'es',
            'norwegian.inc' => 'no',
            'persian.inc' => 'fa',
            'polish.inc' => 'pl',
            'portuguese.inc' => 'pt',
            'portuguese_pt.inc' => 'pt-PT',
            'romanian.inc' => 'ro',
            'russian.inc' => 'ru',
            'serbian.inc' => 'sr',
            'slovenian.inc' => 'sl',
            'spanish.inc' => 'es',
            'swedish.inc' => 'sv',
            'swissgerman.inc' => 'de-CH',
            'tchinese.inc' => 'zh-TW',
            'turkish.inc' => 'tr',
            'ukrainian.inc' => 'uk',
            'usa.inc' => 'en',
            'vietnamese.inc' => 'vi',
        );

        return isset($fileToCode[$languageFile]) ? $fileToCode[$languageFile] : '';
    }

    /**
     * Class constructor.
     * Initialises some dynamic variables.
     */
    public function __construct()
    {
        $this->coderoot = __DIR__ . '/' . __CLASS__ . '/';

        parent::__construct();

        $this->version = (is_file($f = $this->coderoot . self::VERSION_FILE))
            ? file_get_contents($f)
            : '';
    }

    /**
     * Provide the dependencies for enabling this plugin.
     *
     * @return array
     */
    public function dependencyCheck()
    {
        global $plugins;

        return array(
            'Common Plugin v3.28.0 or later installed' => (
                phpListPlugin::isEnabled('CommonPlugin')
                && version_compare($plugins['CommonPlugin']->version, '3.28.0') >= 0
            ),
            'phpList version 3.6.11 or later' => version_compare(VERSION, '3.6.11') >= 0,
        );
    }

    /**
     * Cache the plugin's config settings.
     * hCaptcha will be used only when both the site key and secrety key have
     * been entered.
     */
    public function activate()
    {
        $this->settings = array(
            'hcaptcha_sitekey' => array(
                'description' => s('hCaptcha site key'),
                'type' => 'text',
                'value' => '',
                'allowempty' => false,
                'category' => 'hCaptcha',
            ),
            'hcaptcha_secretkey' => array(
                'description' => s('hCaptcha secret key'),
                'type' => 'text',
                'value' => '',
                'allowempty' => false,
                'category' => 'hCaptcha',
            ),
        );

        parent::activate();

        $this->siteKey = getConfig('hcaptcha_sitekey');
        $this->secretKey = getConfig('hcaptcha_secretkey');
        $this->keysEntered = $this->siteKey !== '' && $this->secretKey !== '';
    }

    /**
     * Provide the hCaptcha html to be included in a subscription page.
     *
     * @param array $pageData subscribe page fields
     * @param int   $userId   user id
     *
     * @return string
     */
    public function displaySubscriptionChoice($pageData, $userID = 0)
    {
        if (empty($pageData['hcaptcha_include'])) {
            return '';
        }

        if (!$this->keysEntered) {
            return '';
        }
        $apiUrl = 'https://hcaptcha.com/1/api.js';

        if (isset($pageData['language_file'])) {
            $languageCode = $this->languageCode($pageData['language_file']);

            if ($languageCode !== '') {
                $apiUrl .= "?hl=$languageCode";
            }
        }
        $format = <<<'END'
<div class="h-captcha" data-sitekey="%s" data-size="%s" data-theme="%s"></div>
<script type="text/javascript" src="%s" async defer></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script type="text/javascript">
$( document ).ready(function() {
    $("form[name=subscribeform]").submit(function(ev) {
        if (grecaptcha.getResponse() != "") {
            return true;
        }
        alert("%s");
        return false;
    });
});

</script>
END;
        $translator = new FrontendTranslator($pageData, $this->coderoot);

        return sprintf(
            $format,
            $this->siteKey,
            $pageData['hcaptcha_size'],
            $pageData['hcaptcha_theme'],
            $apiUrl,
            addslashes($translator->s('Please complete the hCaptcha'))
        );
    }

    /**
     * Provide additional validation when a subscribe page has been submitted.
     *
     * @param array $pageData subscribe page fields
     *
     * @return string an error message to be displayed or an empty string
     *                when validation is successful
     */
    public function validateSubscriptionPage($pageData)
    {
        if (empty($pageData['hcaptcha_include'])) {
            return '';
        }

        if ($_GET['p'] == 'asubscribe' && !empty($pageData['hcaptcha_not_asubscribe'])) {
            return '';
        }

        if (!$this->keysEntered) {
            return '';
        }

        if (empty($_POST['h-captcha-response'])) {
            $translator = new FrontendTranslator($pageData, $this->coderoot);

            return $translator->s('Please complete the hCaptcha');
        }
        $data = [
            'secret' => $this->secretKey,
            'response' => $_POST['h-captcha-response'],
        ];
        $request = new HTTP_Request2('https://hcaptcha.com/siteverify', HTTP_Request2::METHOD_POST);
        $request->addPostParameter($data);
        $logOutput = '';
        $request->attach(new HTTP_Request2_Observer_Log(StringStream::fopen($logOutput, 'w')));
        $response = $request->send();
        Logger::instance()->debug("\n" . $logOutput);
        $responseData = json_decode($response->getBody());

        if ($responseData->success) {
            return '';
        }

        return isset($responseData->{'error-codes'})
            ? implode(', ', $responseData->{'error-codes'})
            : 'unspecified error';
    }

    /**
     * Provide html for the hCaptcha options when editing a subscribe page.
     *
     * @param array $pageData subscribe page fields
     *
     * @return string additional html
     */
    public function displaySubscribepageEdit($pageData)
    {
        $include = isset($pageData['hcaptcha_include']) ? (bool) $pageData['hcaptcha_include'] : false;
        $notAsubscribe = isset($pageData['hcaptcha_not_asubscribe']) ? (bool) $pageData['hcaptcha_not_asubscribe'] : true;
        $theme = isset($pageData['hcaptcha_theme']) ? $pageData['hcaptcha_theme'] : 'light';
        $size = isset($pageData['hcaptcha_size']) ? $pageData['hcaptcha_size'] : 'normal';
        $html =
            CHtml::label(s('Include hCaptcha in the subscribe page'), 'hcaptcha_include')
            . CHtml::checkBox('hcaptcha_include', $include, array('value' => 1, 'uncheckValue' => 0))
            . '<p></p>'
            . CHtml::label(s('Do not validate hCaptcha for asubscribe'), 'hcaptcha_not_asubscribe')
            . CHtml::checkBox('hcaptcha_not_asubscribe', $notAsubscribe, array('value' => 1, 'uncheckValue' => 0))
            . CHtml::label(s('The colour theme of the hCaptcha widget'), 'hcaptcha_theme')
            . CHtml::dropDownList('hcaptcha_theme', $theme, array('light' => 'light', 'dark' => 'dark'))
            . CHtml::label(s('The size of the hCaptcha widget'), 'hcaptcha_size')
            . CHtml::dropDownList('hcaptcha_size', $size, array('normal' => 'normal', 'compact' => 'compact'));

        return $html;
    }

    /**
     * Save the hCaptcha settings.
     *
     * @param int $id subscribe page id
     */
    public function processSubscribePageEdit($id)
    {
        global $tables;

        Sql_Query(
            sprintf('
                REPLACE INTO %s
                (id, name, data)
                VALUES
                (%d, "hcaptcha_include", "%s"),
                (%d, "hcaptcha_not_asubscribe", "%s"),
                (%d, "hcaptcha_theme", "%s"),
                (%d, "hcaptcha_size", "%s")
                ',
                $tables['subscribepage_data'],
                $id,
                $_POST['hcaptcha_include'],
                $id,
                $_POST['hcaptcha_not_asubscribe'],
                $id,
                $_POST['hcaptcha_theme'],
                $id,
                $_POST['hcaptcha_size']
            )
        );
    }
}
