<?php
/**
 * Allow to put comments on survey for each response
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2019 Denis Chenu <https://www.sondages.pro>
 * @copyright 2019 OECD <https://www.oecd.org/>
 * @license GPL v3
 * @version 0.0.1
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
class commentsOnSurvey extends PluginBase {

    static protected $description = 'Allow admin user or token user to put comments on survey';
    static protected $name = 'commentsOnSurvey';

    static protected $dbVersion = 1;

    protected $storage = 'DbStorage';

    protected $settings = array(
        'information' => array(
            'type' => 'info',
            'content' => 'The default settings for all surveys. You can update it in each survey plugin setting.',
        ),
        'allowAdminUser' => array(
            'type'=>'checkbox',
            'htmlOptions'=>array(
                'value'=>1,
                'uncheckValue'=>0,
            ),
            'label' => "Allow limesurvey admin user to create or update comment in survey.",
            'help' => "If you disable this option : this disable totally the comments.",
            'default'=>1,
        ),
        //~ 'allowGroupManagerUser' => array(
            //~ 'type'=>'checkbox',
            //~ 'htmlOptions'=>array(
                //~ 'value'=>1,
                //~ 'uncheckValue'=>0,
            //~ ),
            //~ 'label'=>"Allow group manager to comment on survey.",
            //~ 'help' => "Only available with responseListAndManage plugin",
            //~ 'default'=>0,
        //~ ),
        'allowTokenUserCreate' => array(
            'type'=>'checkbox',
            'htmlOptions'=>array(
                'value'=>1,
                'uncheckValue'=>0,
            ),
            'label'=>"Allow participant to create comment in survey.",
            'default'=>0,
        ),
        'allowTokenUserUpdate' => array(
            'type'=>'checkbox',
            'htmlOptions'=>array(
                'value'=>1,
                'uncheckValue'=>0,
            ),
            'label'=>"Allow participant to update existing comments in survey.",
            'help' => "Always allow participant to set comments as read.",
            'default'=>0,
        ),
        //~ 'keepAllComments' => array(
            //~ 'type'=>'checkbox',
            //~ 'htmlOptions'=>array(
                //~ 'value'=>1,
                //~ 'uncheckValue'=>0,
            //~ ),
            //~ 'label'=>"Keep in database all comments done.",
            //~ 'default'=>0,
        //~ ),
    );
    public function init() {
        /* DB creation */
        $this->subscribe('beforeActivate');

        /* Don't do other action on console */
        if (Yii::app() instanceof CConsoleApplication) {
            return;
        }

        /* Register the model */
        $this->subscribe('afterPluginLoad');

        /* question part */
        $this->subscribe('beforeQuestionRender');
        $this->subscribe('newQuestionAttributes','addCommentAttribute');
        $this->subscribe('getPluginTwigPath');

        /* Survey settings */
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        
        //$this->subscribe('newDirectRequest');
        /* Manage submit */
        $this->subscribe('beforeSurveyPage');
    }

    /** @inheritdoc **/
    public function getPluginSettings($getValues=true)
    {
        /* @todo translation of label and help */
        return parent::getPluginSettings($getValues);
    }

    /** @inheritdoc **/
    public function saveSettings($settings)
    {
        return parent::saveSettings($settings);
    }

    /**
     * Create the DB when activate
     */
    public function beforeActivate()
    {
        $this->getEvent()->set("success",$this->_setDb());
    }

    public function afterPluginLoad()
    {
        Yii::setPathOfAlias(get_class($this), dirname(__FILE__));
        //~ $twigRenderer = Yii::app()->getComponent('twigRenderer');
        //~ $twigRenderer->sandboxConfig['methods']['ETwigViewRendererStaticClassProxy'][] = 'getIdByName';
        //~ Yii::app()->setComponent('twigRenderer',$twigRenderer);
    }

    /* @see plugin event */
    public function getPluginTwigPath() 
    {
        $viewPath = dirname(__FILE__)."/views";
        $this->getEvent()->append('add', array($viewPath));
    }

    /**
     * Show the survey settings
     */
    public function beforeSurveySettings()
    {

    }

    /**
     * Save the survey settings
     */
    public function newSurveySettings()
    {

    }

    /**
     * Add the textarea and the checkbox inside question HTML
     */
    public function beforeQuestionRender()
    {
        $sid = $this->getEvent()->get('surveyId');
        if(empty($_SESSION['survey_'.$sid]['srid'])) {
            return;
        }
        if(!$this->_getSurveySetting('allowAdminUser',$sid)) {
            return;
        }

        $srid = $_SESSION['survey_'.$sid]['srid'];
        $qid = $this->getEvent()->get('qid');
        $aAttributes = \QuestionAttribute::model()->getQuestionAttributes($qid);
        if(!empty($aAttributes['commentsOnSurvey_comments']) || !empty($aAttributes['commentsOnSurvey_commentsShow'])) {
            $isAdmin = \Permission::model()->hasSurveyPermission($sid,'response','update');
            $currentComment = \commentsOnSurvey\models\questionsComments::getCurrentComment($sid,$qid,$srid);
            if(empty($currentComment) && !$isAdmin && !$this->_getSurveySetting('allowTokenUserCreate',$sid)) {
                return;
            }
            if(!empty($currentComment)) {
                $currentComment->getAttributes();
            }
            $allowCreate = ($isAdmin || $this->_getSurveySetting('allowTokenUserCreate',$sid));
            $allowUpdate = ($isAdmin || $this->_getSurveySetting('allowTokenUserUpdate',$sid));
            $aSurveyinfo = getSurveyInfo($sid, App()->getLanguage());
            /* event are updated to getPluginTwigPath */
            $questionEvent = $this->getEvent();
            $currentQuestionText = $questionEvent->get('text');
            $commentsHtml = Yii::app()->twigRenderer->renderPartial('/subviews/survey/question_subviews/comments_on_survey.twig', array(
                'aSurveyInfo' => getSurveyInfo($sid, App()->getLanguage()),
                'allowCreate' => $allowCreate,
                'allowUpdate' => $allowUpdate,
                'currentComment' => $currentComment,
                'commentName' => 'commentOnSurvey['.$qid.']',
                'commentId' => \CHtml::getIdByName('commentOnSurvey['.$qid.']'),
                'deleteName' => 'commentOnSurveyDelete['.$qid.']',
                'deleteId' => \CHtml::getIdByName('commentOnSurveyDelete['.$qid.']'),
                'lang'=> array(
                    'Comments on this question.' => $this->_translate('Comments on this question.'),
                    'Set as read.' => $this->_translate('Set as read.'),
                    'This delete comment' => $this->_translate('This delete comment'),
                ),
            ));
            $questionEvent->set('text',$currentQuestionText.$commentsHtml);
        }
    }

    /**
     * Add the option in each question
     */
    public function addCommentAttribute()
    {
        $questionAttributes['commentsOnSurvey_comments']=array(
            "types"=>'15ABCDEFGHIKLMNOPQRSTUWYZ!:;|',
            'category'=>$this->_translate('Comments'),
            'sortorder'=>1,
            'inputtype'=>'switch',
            'options'=>array(0=>gT('No'), 1=>gT('Yes')),
            'default'=>1,
            "help"=>$this->_translate("Show the comment according to survey settings."),
            "caption"=>$this->_translate('Show the comment area.'),
        );
        $questionAttributes['commentsOnSurvey_commentsShow']=array(
            "types"=>'X*',
            'category'=>$this->_translate('Comments'),
            'sortorder'=>1,
            'inputtype'=>'switch',
            'options'=> array(0=>gT('No'), 1=>gT('Yes')),
            'default'=>0,
            "help"=>$this->_translate("Show the comment according to survey settings."),
            "caption"=>$this->_translate('Show the comment area.'),
        );
        $this->getEvent()->append('questionAttributes', $questionAttributes);
    }

    /**
     * Manage POST values
     */
    public function beforeSurveyPage()
    {
        $commentsOnSurvey = App()->getrequest()->getPost('commentOnSurvey',array());
        $commentsOnSurveyDelete = App()->getrequest()->getPost('commentOnSurveyDelete',array());
        if(empty($commentsOnSurvey) && empty($commentsOnSurveyDelete)) {
            return;
        }
        $surveyId = $this->getEvent()->get('surveyId');
        if(!$this->_getSurveySetting('allowAdminUser',$surveyId)) {
            return;
        }
        if(empty($_SESSION['survey_'.$surveyId]['srid'])) {
            return;
        }
        $srid = $_SESSION['survey_'.$surveyId]['srid'];
        if($this->_getSurveySetting('allowTokenUserUpdate',$surveyId) && !\Permission::model()->hasSurveyPermission($surveyId,'response','update')) {
            $commentsOnSurvey =array();
        }
        foreach($commentsOnSurvey as $qid => $commentOnSurvey) {
            // To do check if exist with only allowTokenUserUpdate but not allowTokenUserCreate
            $currentComment = \commentsOnSurvey\models\questionsComments::getCurrentComment($surveyId,$qid,$srid);
            // Todo : add a "update" checkbox : more clear and simple and less control is always better
            if(!empty($currentComment)) {
                if($this->_replaceNewLine($commentOnSurvey) == $this->_replaceNewLine($currentComment->comment)) {
                    continue;
                }
            }
            $oComment = \commentsOnSurvey\models\questionsComments::setCurrentComment($surveyId,$qid,$srid,$commentOnSurvey);
        }
        foreach($commentsOnSurveyDelete as $qid => $commentOnSurveyDelete) {
            $currentComment = \commentsOnSurvey\models\questionsComments::getCurrentComment($surveyId,$qid,$srid);
            if(!empty($currentComment)) {
                $now = dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig('timeadjust'));
                \commentsOnSurvey\models\questionsComments::model()->updateByPk($currentComment->id,array("readed" => $now));
            }
        }
    }

    /**
     * Create or update the DB
     */
    private function _setDb()
    {
        if($this->get("dbVersion") == self::$dbVersion) {
            return;
        }
        $sCollation = '';
        if (Yii::app()->db->driverName == 'mysql' || Yii::app()->db->driverName == 'mysqli') {
            $sCollation = "COLLATE 'utf8mb4_bin'";
        }
        if (Yii::app()->db->driverName == 'sqlsrv'
            || Yii::app()->db->driverName == 'dblib'
            || Yii::app()->db->driverName == 'mssql') {

            $sCollation = "COLLATE SQL_Latin1_General_CP1_CS_AS";
        }
        /* dbVersion not needed */
        if(!$this->api->tableExists($this, 'questionsComments')) {
            $this->api->createTable($this, 'questionsComments', array(
                'id'=>'pk',
                'sid'=>'int not NULL',
                'qid'=>'int not NULL',
                'srid'=>'int not NULL',
                'comment'=>'text',
                'created'=>'datetime', // DEFAULT CURRENT
                'readed'=>'datetime', // DEFAULT NULL ?
                'authortype' => 'string(1)',
                'author' => "string(35) {$sCollation}",
            ));
            //$tableName  
            Yii::app()->getDb()->createCommand()->createIndex('{{idx1_questionscomments}}', '{{commentsonsurvey_questionsComments}}', ['qid', 'sid','srid'], false);
            Yii::app()->getDb()->createCommand()->createIndex('{{idx2_questionscomments}}', '{{commentsonsurvey_questionsComments}}', ['qid', 'sid','srid','created'], false);
            Yii::app()->getDb()->createCommand()->createIndex('{{idx3_questionscomments}}', '{{commentsonsurvey_questionsComments}}', ['qid', 'sid','srid','readed'], false);
            $this->set("dbVersion",1);
        }
        $this->set("dbVersion",self::$dbVersion);
        return true;
    }

    /**
     * Get final setting for survey
     * @param string setting to get
     * @param integer survey id
     * @return mixed
     */
    private function _getSurveySetting($setting,$surveyid)
    {
        $value = $this->get($setting,'Survey',$surveyid,
            $this->get($setting,null,null,$this->settings[$setting]['default'])
        );
        if($value === "") {
            return $this->get($setting,null,null,$this->settings[$setting]['default']);
        }
        return $value;
    }
    /**
     * Translate a string
     * @param string to translate
     * @param string escape mode
     * @parm string language to use
     * @return string
     */
    private function _translate($string, $sEscapeMode = 'unescaped', $sLanguage = NULL) {
        if(intval(Yii::app()->getConfig('versionnumber')) >= 3) {
            return parent::gT($string, $sEscapeMode, $sLanguage );
        }
        return $string;
    }

  /**
   * replace new line to specific new line, used to compare strings difference between loaded by DB and send by browser
   * @param string $string to fix
   * @param string $newline replace by
   * @return string
   */
  private static function _replaceNewLine($string,$newline="\n") {
    if (version_compare(substr(PCRE_VERSION,0,strpos(PCRE_VERSION,' ')),'7.0')>-1) {
      return trim(preg_replace(array('~\R~u'),array($newline), $string));
    }
    return trim(str_replace(array("\r\n","\n", "\r"), array($newline,$newline,$newline), $string));
  }
}
