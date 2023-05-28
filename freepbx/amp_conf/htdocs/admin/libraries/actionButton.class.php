<?php
namespace FreePBX\libraries;
/**
* Class actionButton
*
* Example:
*	$b = new actionButton();
*  	$b->setParams($buttonArray);
* 	$b->getHTML();
*
* Button Array:
* 	$buttonArray = array(
* 		'name' => 'Submit',
* 		'value' => 'Submit',
*		'id' => 'submitbutton',
* 		'class' => array(
* 			'button',
* 			'fooclass'
* 			),
* 		);
*
* Methods:
* @actionButton::setParams()
* 	Accepts an array of parameters used to generate buttons.
* @actionButton::getHTML()
* 	Returns button output based on the set parameters.
*/

class actionButton{
	public function	__construct(){
		$this->name = '';
		$this->type = 'submit';
		$this->value = '';
		$this->id = '';
		$this->class = array();
		$this->data = array();
		$this->form = '';
		$this->value = 'Submit';
		$this->formaction = '';
		$this->autofocus = '';
		$this->formenctype = '';
		$this->formmethod = '';
		$this->formtarget = '';
		$this->formnovalidate = '';
		$this->onclick = '';
		$this->onsubmit = '';
	}
	public function getHTML(){
		$button = array();
		$button[] = "<input ";
		foreach($this as $key => $val){
			if(empty($val)){ continue;}
				if(is_array($val)){
					switch($key){
						case "data":
							foreach($val as $dk => $dv){
								$button[] = 'data-' . $dk . '="' . $dv .'"';
							}
							break;
						default:
							$button[] = $key . '="' . implode(" ",$val) . '"';
							break;
					}
				}else{
					$button[] = $key . '="' . $val . '"';
				}
		}
		$button[] = '>';
		return implode(" ", $button);
	}
	public function setParams($button){
		foreach($button as $key => $val){
			if(is_array($val)){
				$this->{$key} = &$val;
			}else{
				$this->{$key} = $val;
			}
		}
	}
}
