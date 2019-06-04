<?php
/**
 * This file is part of reloadAnyResponse plugin
 * @version 1.0.2
 */
namespace commentsOnSurvey\models;
use Yii;
use CActiveRecord;
class questionsComments extends CActiveRecord
{
    /**
     * Class surveyChaining\models\chainingResponseLink
     *
     * @property integer $id comment id
     * @property integer $sid survey
     * @property integer $qid question
     * @property integer $srid : response id
     * @property string $comment : the comments
     * @property created $datetime datetime of the comment
     * @property readed $datetime datetime of read
     * @property string $authortype : type : [t=>token,a=>admin] // Currently unused
     * @property string $author author of comment : token of this survey or admin id // Currently unused
    */

    /** @inheritdoc */
    public static function model($className=__CLASS__) {
        return parent::model($className);
    }
    /** @inheritdoc */
    public function tableName()
    {
        return '{{commentsonsurvey_questionsComments}}';
    }

    /** @inheritdoc */
    public function primaryKey()
    {
        return array('id');
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $aRules = array(
            array('sid', 'required'),
            array('srid', 'required'),
            array('sid,qid,srid', 'numerical', 'integerOnly'=>true),
        );
        return $aRules;
    }

    /**
     * Get the current comment for this question
     * @param $sid survey id
     * @param $qid question id
     * @param $srid response id
     * @return null|self::model
     **/
    public static function getCurrentComment($sid,$qid,$srid)
    {
        $oComment = self::model()->find(
            "sid = :sid AND qid = :qid AND srid = :srid AND readed IS NULL",
            array(':sid'=>$sid,':qid'=>$qid,':srid'=>$srid)
        );
        return $oComment;
    }

    /**
     * Set the current comment for this question
     * @param $sid survey id
     * @param $qid question id
     * @param $srid response id
     * @return self::model()
     **/
    public static function setCurrentComment($sid,$qid,$srid,$comment,$authortype = null, $author = null)
    {
        if(empty($comment)) {
            return;
        }
        $oComment = new self;
        $oComment->sid = $sid;
        $oComment->qid = $qid;
        $oComment->srid = $srid;
        $oComment->created = dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig('timeadjust'));
        $oComment->comment = $comment;
        if(!$oComment->save()) {
            // Todo : throw error or log it as error (debug set)
        }
        if($oComment->save()) {
            $now = dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig('timeadjust'));
            self::model()->updateAll(
                array('readed'=>$now),
                "sid = :sid AND qid = :qid AND srid = :srid AND readed IS NULL AND id != :id",
                array(':sid'=>$sid,':qid'=>$qid,':srid'=>$srid,':id'=>$oComment->id)
            );
        } else {

        }
        return $oComment;
    }

    /**
     * Set readed as NOW : why this methode don't work ???
     */
    public function setAsRead()
    {
        $now = dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig('timeadjust'));
        $this->readed = $now;
        if(!$this->save()) {
            // Don' return error but don't save
            //~ print_r($this->getErrors());
        }
    }
}
