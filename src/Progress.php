<?php
/**
 * @brief spamplemousse2, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul and contributors
 *
 * @copyright Franck Paul carnet.franck.paul@gmail.com
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\spamplemousse2;

use dcPage;
use Dotclear\Helper\Html\XmlTag;
use Exception;

/**
This class implements a progress bar system for some lengthy php scripts.
 */
class Progress
{
    private string $title;
    private string $urlprefix;
    private string $urlreturn;
    private array $func;
    private int $start;
    private int $stop;
    private int $baseinc;
    private int $pos;
    private bool $first_run = false;
    private float $total_elapsed;
    private float $total_time;
    private float $percent = 0;
    private float $eta;
    private string $nonce;
    private string $formparams;

    /**
     * Constructs a new instance.
     *
     * @param      string      $title       The title of the page
     * @param      string      $urlprefix   The prefix for all urls
     * @param      string      $urlreturn   The URL for quitting the interface
     * @param      array       $func        The Static method to call (e.g. array('class', 'method')
     * @param      int         $start       The Id of the starting point
     * @param      int         $stop        The Id of the end point
     * @param      int         $baseinc     The number of items to process on each loop
     * @param      string      $nonce       The session token
     * @param      int         $pos         The current position (in order to resume processing)
     * @param      string      $formparams  The parameters to add to the form
     *
     * Note: the func item method must have two parameters "limit" and "offset" like in a sql query
     */
    public function __construct(string $title, string $urlprefix, string $urlreturn, array $func, int $start, int $stop, int $baseinc, string $nonce, int $pos = 0, string $formparams = '')
    {
        $this->start = !empty($_POST['start']) ? $_POST['start'] : $start;
        if ($_POST['pos'] != '') {
            $this->pos = (int) $_POST['pos'];
        } elseif ($pos) {
            $this->pos       = $pos;
            $this->first_run = true;
        } else {
            $this->pos       = $start;
            $this->first_run = true;
        }
        $this->stop          = !empty($_POST['stop']) ? (int) $_POST['stop'] : $stop;
        $this->total_elapsed = !empty($_POST['total_elapsed']) ? (float) $_POST['total_elapsed'] : 0;
        $this->total_time    = (float) ini_get('max_execution_time') / 4;
        $this->title         = $title;
        $this->urlprefix     = $urlprefix;
        $this->urlreturn     = $urlreturn;
        $this->formparams    = $formparams;
        $this->nonce         = $nonce;
        $this->func          = $func;
        $this->baseinc       = $baseinc;
    }

    /**
     * Display the progress interface
     *
     * @param      string  $content  The content of the âge
     *
     * @return     string  The content after modification.
     */
    public function gui(string $content): string
    {
        $content .= '<h3>' . $this->title . '</h3>';
        $error = '';

        $return = '<a id="return" ';
        if ($this->pos < $this->stop) {
            $return .= 'style="display: none;"';
        }
        $return .= ' href="' . $this->urlreturn . '">' . __('Return') . '</a>';

        if (!$this->first_run) {
            if ($this->pos >= $this->stop) {
                $content .= $return;

                return $content;
            }

            try {
                $this->compute();
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        if ($error != '') {
            $content .= '<p class="message">' . __('Error:') . ' ' . $error . '</p>';
        } else {
            $content .= dcPage::jsModuleLoad(My::id() . '/js/progress.js');

            // display informations
            $content .= '<p>' . __('Progress:') . ' <span id="percent">' . sprintf('%d', $this->percent) . '</span> %</p>';
            $content .= '<p>' . __('Time remaining:') . ' <span id="eta">';
            if ($this->percent != 0) {
                $content .= sprintf('%d', $this->eta) . ' s';
            }
            $content .= '</span></p>';

            $content .= '<form action="' . $this->urlprefix . '" method="post">' .
                        $this->formparams .
                        '<input type="hidden" name="pos" value="' . $this->pos . '" />' .
                        '<input type="hidden" name="start" value="' . $this->start . '" />' .
                        '<input type="hidden" name="stop" value="' . $this->stop . '" />' .
                        '<input type="hidden" name="total_elapsed" value="' . $this->total_elapsed . '" />' .
                        '<input type="hidden" name="xd_check" value="' . $this->nonce . '" />' .
                        '<input type="submit" id="next" value="' . __('Continue') . '" />' .
                        '</form>';

            $content .= $return;

            $content .= '<script type="text/javascript">' .
                    '$(function() {' .
                    '	$(\'#next\').hide(); ' .
                    '	progressUpdate(\'' . $this->func[0] . '\', \'' . $this->func[1] . '\', ' . $this->pos . ', ' . $this->start . ', ' . $this->stop . ', ' . $this->baseinc . ', \'' . $this->nonce . '\');' .
                    '});</script>';
        }

        return $content;
    }

    /**
     * Rest interface
     *
     * @return     XmlTag  Xml message.
     */
    public function toXml(): XmlTag
    {
        $rsp   = new XmlTag();
        $error = '';

        if (!$this->first_run) {
            if ($this->pos >= $this->stop) {
                $return_xml = new XmlTag('return');
                $return_xml->insertNode($this->urlreturn);
                $rsp->insertNode($return_xml);

                return $rsp;
            }

            try {
                $this->compute();
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        if ($error != '') {
            $error_xml = new XmlTag('error');
            $error_xml->insertNode($error);
            $rsp->insertNode($error_xml);
        } else {
            $percent_xml = new XmlTag('percent');
            $percent_xml->insertNode(sprintf('%d', $this->percent));
            $rsp->insertNode($percent_xml);
            if ($this->percent != 0) {
                $eta_xml = new XmlTag('eta');
                $eta_xml->insertNode(sprintf('%d', $this->eta));
                $rsp->insertNode($eta_xml);
            }
            $pos_xml = new XmlTag('pos');
            $pos_xml->insertNode((string) $this->pos);
            $rsp->insertNode($pos_xml);

            $total_xml = new XmlTag('total_elapsed');
            $total_xml->insertNode((string) $this->total_elapsed);
            $rsp->insertNode($total_xml);

            $start_xml = new XmlTag('start');
            $start_xml->insertNode((string) $this->start);
            $rsp->insertNode($start_xml);

            $stop_xml = new XmlTag('stop');
            $stop_xml->insertNode((string) $this->stop);
            $rsp->insertNode($stop_xml);

            $baseinc_xml = new XmlTag('baseinc');
            $baseinc_xml->insertNode((string) $this->baseinc);
            $rsp->insertNode($baseinc_xml);

            $funcClass_xml = new XmlTag('funcClass');
            $funcClass_xml->insertNode($this->func[0]);
            $rsp->insertNode($funcClass_xml);

            $funcMethod_xml = new XmlTag('funcMethod');
            $funcMethod_xml->insertNode($this->func[1]);
            $rsp->insertNode($funcMethod_xml);

            $nonce_xml = new XmlTag('nonce');
            $nonce_xml->insertNode($this->nonce);
            $rsp->insertNode($nonce_xml);
        }

        return $rsp;
    }

    /**
     * Call the given method
     */
    private function compute()
    {
        $elapsed = 0;
        do {
            $loopParams = [];
            $end        = $this->pos + $this->baseinc;
            if ($end > $this->stop) {
                $end = $this->stop;
            }
            $loopParams[] = $this->baseinc;
            $loopParams[] = $this->pos;
            $this->pos    = $end;
            $tstart       = microtime(true);
            call_user_func_array($this->func, $loopParams);
            $tend = microtime(true);
            $elapsed += $tend - $tstart;
        } while (($elapsed < $this->total_time) && ($this->pos < $this->stop));
        $this->total_elapsed += $elapsed;

        $this->percent = ($this->pos - $this->start) / ($this->stop - $this->start) * 100;
        if ($this->percent != 0) {
            $this->eta = 100 * $this->total_elapsed / $this->percent - $this->total_elapsed;
        }
    }
}
