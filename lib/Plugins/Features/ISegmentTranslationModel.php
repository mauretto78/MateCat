<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/01/2019
 * Time: 15:34
 */


namespace Features;

interface ISegmentTranslationModel {
    public function addOrSubtractCachedReviewedWordsCount();
    public function recountPenaltyPoints();
}