<?php

namespace L0rD59\Behat\LdapExtension;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Context\TranslatableContext;
use Behat\Gherkin\Node\TableNode;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Ldap;

class Context implements TranslatableContext, SnippetAcceptingContext
{
  /**
   * @var Ldap
   */
  protected $client;

  /**
   * @var string $rootDn dn root entry.
   */
  protected $rootDn;

  /**
   * @var boolean $bind_before_scenarion
   */
  protected $bind_before_scenarion;

  /**
   * @var string $purge_before_scenario
   */
  protected $purge_before_scenario;

  /**
   * @var array $authentication ['rdn','password']
   */
  protected $authentication;

  /**
   * @var Entry $request_results
   */
  private $request_results;
  
  public static function getTranslationResources()
  {
    return glob(__DIR__.'/i18n/*.xliff');
  }

  /**
   * Sets configuration of the context.
   *
   * @param Ldap  $client client to use for API.
   * @param string  $rootDn dn root entry.
   * @param boolean  $bind_before_scenario dn root entry.
   * @param boolean  $purge_before_scenario dn root entry.
   * @param array|null  $authentication ['rdn','password'].
   */
  public function setConfiguration(Ldap $client, $rootDn, $bind_before_scenario, $purge_before_scenario, $authentication)
  {
    $this->client = $client;
    $this->rootDn = $rootDn;
    $this->authentication = $authentication;
    $this->bind_before_scenarion = $bind_before_scenario;
    $this->purge_before_scenario = $purge_before_scenario;
  }

  /**
   * @BeforeScenario
   */
  public function beforeScenario()
  {
      if($this->bind_before_scenarion){
        $this->client->bind($this->authentication['rdn'], $this->authentication['password']);
      }

    if($this->purge_before_scenario)
    {
    }
  }

  /**
   * Creates entries provided in the form:
   * | cn    | attribute1    | attribute2 | attributeN |
   * | primary | value1 | value2 | valueN |
   * | ...      | ...        | ...  | ... |
   *
   * @Given /^Ldap entries:$/
   */
  public function ldapEntries(TableNode $entries)
  {
    foreach ($entries->getHash() as $entry) {
      $ldapEntry = new Entry('cn'.'='.$entry['cn'].','.$this->rootDn, $entry);
      $this->client->getEntryManager()->add($ldapEntry);
    }
  }

  /**
   * @Then /^Ldap request "(?P<request>[^"]+)" should return (?P<integer>\d+) results$/
   */
  public function ldapRequestShouldReturnResults($request, $count)
  {
    if(is_null($results = $this->client->query($this->rootDn, $request)->execute()) || $results->count() != $count )
    {
      throw new \Exception('Ldap request "'.$request.'" has return '.$results->count().'result(s)');
    }
  }

   /**
   * @Then /^The "(?P<objectclass>[^"]+)" with cn "(?P<cn>[^"]+)" should exist in Ldap$/
   */
  public function theObjectclassWithCnShouldExistInLdap($objectclass, $cn)
  {
    if(is_null($results = $this->client->query($this->rootDn, '(cn='.$cn.')')->execute()))
    {
      throw new \Exception('Unknow entry cn='.$cn.' in Ldap');
    }

    $results = $results->toArray();

    if(! in_array($objectclass, $results[0]->getAttribute('objectClass'), true))
    {
      throw new \Exception('The entry cn='.$cn.' is not a '.$objectclass.' ('.implode(',', $results[0]->getAttribute('objectClass')).')');
    }
  }
  
   /**
   * @When /^I search Ldap for "(?P<request>[^"]+)"$/
   */
  public function iSearchLdapFor($request)
  {
    if(is_null($this->request_results = $this->client->query($this->rootDn, $request)->execute()) )
    {
      throw new \Exception('Ldap request "'.$request.'" has failed ');
    }
  }
  
  /**
   * @Then /^I should get (?P<integer>\d+) entries$/
   */
  public function iShouldGetEntries($count)
  {
    if($this->request_results->count() != $count )
    {
      throw new \Exception('Ldap request has returned '.$this->request_results->count().'entries');
    }
  }
  
  /**
   * @Then /^The entries should all have attribute "(?P<attribute_name>[^"]+)" with value "(?P<attribute_value>[^"]+)"$/
   */
  public function theEntriesShouldAllHaveAttributeWithValue($attribute_name, $attribute_value)
  {
  
    foreach ($this->request_results->toArray() as $entry) {
    
      if(! $entry->hasAttribute($attribute_name)) {
        throw new \Exception('Entry '.$entry->getDn().' has no attribute '.$attribute_name);
      }
    
      if(! in_array($attribute_value, $entry->getAttribute($attribute_name), true))
      {
        throw new \Exception('Entries '.$entry->getDn().' has '.$attribute_name.' with values '.implode(',', $entry->getAttribute($attribute_name)));
      }
    }
  }
  
  /**
   * @Then /^The entries should all have attribute "(?P<attribute_name>[^"]+)" defined$/
   */
  public function theEntriesShouldAllHaveAttributeDefined($attribute_name)
  {
  
    foreach ($this->request_results->toArray() as $entry) {
    
      if(! $entry->hasAttribute($attribute_name)) {
        throw new \Exception('Entry '.$entry->getDn().' has no attribute '.$attribute_name);
      }
    }
  }
}