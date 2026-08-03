<?php use Sigesp\Core\{View}; use Sigesp\Shared\Presentation\DemoData; $page=DemoData::module('responsaveis','novo'); $title=$page['title']; View::component('module-screen',['page'=>$page]);
