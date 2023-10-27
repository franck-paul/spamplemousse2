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

use Dotclear\Core\Backend\Page;
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

    /**
     * @var array{0:string, 1:string}
     */
    private $func;

    private int $start;

    private int $stop;

    private int $baseinc;

    private int $pos;

    private bool $first_run = false;

    private float $total_elapsed;

    private float $total_time;

    private float $percent = 0;

    private float $eta     = 0;

    private string $formparams;

    /**
     * Constructs a new instance.
     *
     * @param      string                       $title       The title of the page
     * @param      string                       $urlprefix   The prefix for all urls
     * @param      string                       $urlreturn   The URL for quitting the interface
     * @param      array{0:string, 1:string}    $func        The Static method to call
     * @param      int                          $start       The Id of the starting point
     * @param      int                          $stop        The Id of the end point
     * @param      int                          $baseinc     The number of items to process on each loop
     * @param      int                          $pos         The current position (in order to resume processing)
     * @param      string                       $formparams  The parameters to add to the form
     *
     * Note: the func item method must have two parameters "limit" and "offset" like in a sql query
     */
    public function __construct(string $title, string $urlprefix, string $urlreturn, array $func, int $start, int $stop, int $baseinc, int $pos = 0, string $formparams = '')
    {
        $this->start = empty($_POST['start']) ? $start : $_POST['start'];
        if ($_POST['pos'] != '') {
            $this->pos = (int) $_POST['pos'];
        } elseif ($pos !== 0) {
            $this->pos       = $pos;
            $this->first_run = true;
        } else {
            $this->pos       = $start;
            $this->first_run = true;
        }

        $this->stop          = empty($_POST['stop']) ? $stop : (int) $_POST['stop'];
        $this->total_elapsed = empty($_POST['total_elapsed']) ? 0 : (float) $_POST['total_elapsed'];
        $this->total_time    = (float) ini_get('max_execution_time') / 4;
        $this->title         = $title;
        $this->urlprefix     = $urlprefix;
        $this->urlreturn     = $urlreturn;
        $this->formparams    = $formparams;
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
                return $content . $return;
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
            $content .= My::jsLoad('progress.js') .
                Page::jsJson('spamplemousse2', [
                    'funcClass'  => $this->func[0],
                    'funcMethod' => $this->func[1],
                    'pos'        => $this->pos,
                    'start'      => $this->start,
                    'stop'       => $this->stop,
                    'baseInc'    => $this->baseinc,
                ]) .
                My::jsLoad('update.js');

            // display informations
            $content .= '<p>' . __('Progress:') . ' <span id="percent">' . sprintf('%d', $this->percent) . '</span> %</p>';
            $content .= '<p>' . __('Time remaining:') . ' <span id="eta">';
            if ($this->percent != 0) {
                $content .= sprintf('%d', $this->eta) . ' s';
            }

            $content .= '</span></p>';

            $content .= '<form action="' . $this->urlprefix . '" method="post">' .
                        $this->formparams .
                        '<input type="submit" id="next" value="' . __('Continue') . '" />' .
                        My::parsedHiddenFields([
                            'pos'           => $this->pos,
                            'start'         => $this->start,
                            'stop'          => $this->stop,
                            'total_elapsed' => (string) $this->total_elapsed,
                        ]) .
                        '</form>';

            $content .= $return;
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
        }

        return $rsp;
    }

    /**
     * Call the given method
     */
    private function compute(): void
    {
        $elapsed = 0;
        do {
            $end = $this->pos + $this->baseinc;
            if ($end > $this->stop) {
                $end = $this->stop;
            }

            $loopParams = [
                $this->baseinc,
                $this->pos,
            ];
            $this->pos = $end;
            $tstart    = microtime(true);
            call_user_func_array($this->func, $loopParams); // @phpstan-ignore-line
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
