<?php

require "meta.php";
require "vendor/autoload.php";

class DbTest extends PHPUnit_Framework_TestCase {

  private $db;
  
  function setUp(){
    $this->db = new Db("root","toor");
  }
  
  function test_user() {
    $this->db->user("foo","bar");
  }
}