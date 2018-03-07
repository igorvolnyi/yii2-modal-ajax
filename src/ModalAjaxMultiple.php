<?php

namespace igorvolnyi\widgets\modal;

use yii\base\InvalidConfigException;
use yii\bootstrap\Modal;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\web\View;
use yii\helpers\ArrayHelper;

/**
 * Class ModalAjaxMultiple
 * @package igorvolnyi\widgets\modal
 * @author Lukyanov Andrey <loveorigami@mail.ru>
 * @author Igor Volnyi <igorvolnyi@gmail.com>
 */
class ModalAjaxMultiple extends Modal
{
    const MODE_SINGLE = 'id';
    const MODE_MULTI = 'multi';

    /**
     * events
     */
    const EVENT_BEFORE_SHOW = 'kbModalBeforeShow';
    const EVENT_MODAL_SHOW = 'kbModalShow';
    const EVENT_BEFORE_SUBMIT = 'kbModalBeforeSubmit';
    const EVENT_MODAL_SUBMIT = 'kbModalSubmit';

    /**
     * @var array
     */
    public $events = [];

    /**
     * The selector to get url request when modal is opened for multy mode
     * @var string
     */
    public $selector;

    /**
     * The url to request when modal is opened for single mode
     * @var string
     */
    public $url;

    /**
     * reload pjax container after ajaxSubmit
     * @var string
     */
    public $pjaxContainer;

    /**
     * Submit the form via ajax
     * @var boolean
     */
    public $ajaxSubmit = true;

    /**
     * Submit the form via ajax
     * @var boolean
     */
    public $autoClose = true;

    /**
     * @var string
     */
    protected $mode = self::MODE_SINGLE;
    
    private $_defaultOptions = [
        'tabindex' => false,
    ];
    
    private $_defaultClientOptions = [
        'backdrop' => 'static',
        'keyboard' => false,
    ];

    /**
     * Renders the header HTML markup of the modal
     * @return string the rendering result
     */
    protected function renderHeader()
    {
        $button = $this->renderCloseButton();
        if ($button !== null) {
            $this->header = $button . "\n<span>" . $this->header . "</span>\n";
        }
        if ($this->header !== null) {
            Html::addCssClass($this->headerOptions, ['widget' => 'modal-header']);
            return Html::tag('div', "\n" . $this->header . "\n", $this->headerOptions);
        } else {
            return null;
        }
    }

    /**
     * @inheritdocs
     */
    public function init()
    {
        $this->options = ArrayHelper::merge($this->_defaultOptions, $this->options);
        
        $this->clientOptions = ArrayHelper::merge($this->_defaultClientOptions, $this->clientOptions);
        
        parent::init();
        
        if (!$this->url && !$this->selector) {
            throw new InvalidConfigException('Not specified property "Url" or "Selector"');
        }

        if ($this->selector) {
            $this->mode = self::MODE_MULTI;
        }
    }

    /**
     * @inheritdocs
     */
    public function run()
    {
        parent::run();
        /** @var View */
        $view = $this->getView();
        $id = $this->options['id'];

        ModalAjaxMultipleAsset::register($view);

        switch ($this->mode) {
            case self::MODE_SINGLE:
                $this->registerSingleModal($id, $view);
                break;

            case self::MODE_MULTI:
                $this->registerMultyModal($id, $view);
                break;
        }
		
		$this->defaultSubmitEvent();

        $this->registerEvents($id, $view);
    }

    /**
     * @param $id
     * @param View $view
     */
    protected function registerSingleModal($id, $view)
    {
        $url = is_array($this->url) ? Url::to($this->url) : $this->url;

        $view->registerJs("
            jQuery('#$id').kbModalAjaxMultiple({
                url: '$url',
                ajaxSubmit: ".($this->ajaxSubmit ? "true" : "false")."
            });
        ");
    }

    /**
     * @param $id
     * @param View $view
     */
    protected function registerMultyModal($id, $view)
    {
        $view->registerJs("
            jQuery('body').on('click', '$this->selector', function(e) {
                $(this).attr('data-toggle', 'modal');
                $(this).attr('data-target', '#$id');
                
                var bs_url = $(this).attr('href');
                var title = $(this).attr('title');
                
                if (title != null && title != '')
				{
					jQuery('#$id').find('.modal-header span').html('<h3 class=\'text-center\'>' + title + '</h3>');
				}
				
                jQuery('#$id').kbModalAjaxMultiple({
                    selector: $(this),
                    url: bs_url,
                    ajaxSubmit: ".($this->ajaxSubmit ? "true" : "false")."
                });
            });
        ");
    }

    /**
     * register pjax event
     */
    protected function defaultSubmitEvent()
    {
        $expression = [];

        if ($this->autoClose) {
            $expression[] = "$(this).modal('hide');";
        }

        if ($this->pjaxContainer) {
            $this->events[] = ["hidden.bs.modal", "function(event) { $.pjax.reload('$this->pjaxContainer'); }"];
        }

        $script = implode("\r\n", $expression);

        $this->events[] = [self::EVENT_MODAL_SUBMIT, new JsExpression("
            function(event, data, status, xhr) {
                if(status){
                    $script
                }
            }
        ")];
    }

    /**
     * @param $id
     * @param View $view
     */
    protected function registerEvents($id, $view)
    {
        $js = [];
        foreach ($this->events as $event) {
            $js[] = ".on('".$event[0]."', ".$event[1].")";
        }

        if ($js) {
            $script = "jQuery('#$id')" . implode("\r\n", $js);
            $view->registerJs($script);
        }
    }
}
