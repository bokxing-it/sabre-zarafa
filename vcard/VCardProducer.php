<?php
/*
 * Copyright 2011 - 2012 Guillaume Lapierre
 * Copyright 2012 - 2013 Bokxing IT, http://www.bokxing-it.nl
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3, 
 * as published by the Free Software Foundation.
 *  
 * "Zarafa" is a registered trademark of Zarafa B.V. 
 *
 * This software use SabreDAV, an open source software distributed
 * with New BSD License. Please see <http://code.google.com/p/sabredav/>
 * for more information about SabreDAV
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *  
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Project page: <http://github.com/bokxing-it/sabre-zarafa/>
 * 
 */

// Logging
include_once ("log4php/Logger.php");
Logger::configure("log4php.xml");
 
require_once "vcard/IVCardProducer.php";
require_once "config.inc.php";
	
// PHP-MAPI
require_once("mapi/mapi.util.php");
require_once("mapi/mapicode.php");
require_once("mapi/mapidefs.php");
require_once("mapi/mapitags.php");
require_once("mapi/mapiguid.php");
	
class VCardProducer implements IVCardProducer {

	public $defaultCharset;
	protected $bridge;
	protected $version;
	protected $logger;
	
	function __construct($bridge, $version) {
		$this->bridge = $bridge;
		$this->version = $version;
		$this->defaultCharset = 'utf-8';
		$this->logger = Logger::getLogger(__CLASS__);
	}

	/**
	 * Decide charset for vcard
	 * conversion is done by the bridge, vobject is always UTF8 encoded
	 */
	public function getDefaultCharset() {
		$this->logger->debug("getDefaultCharset");
		$charset = 'ISO-8859-1//TRANSLIT';
		
		if ($this->version >= 3) {
			$charset = "utf-8";
		} 
		
		$this->logger->debug("Charset: $charset");
		
		return $charset;
	}
	
	/**
	 * Convert vObject to an array of properties
     * @param array $properties 
	 * @param object $vCard
	 */
	public function propertiesToVObject($contact, &$vCard) {

		$this->logger->debug("Generating contact vCard from properties");
		
		$p = $this->bridge->getExtendedProperties();
		$contactProperties =  mapi_getprops($contact); // $this->bridge->getProperties($contactId);
		
		$dump = print_r($contactProperties, true);
		$this->logger->trace("Contact properties:\n$dump");
		
		// Version check
		switch ($this->version) {
			case 2:		$vCard->add('VERSION', '2.1');	break;
			case 3:		$vCard->add('VERSION', '3.0');	break;
			case 4:		$vCard->add('VERSION', '4.0');	break;
			default:
				$this->logger->fatal("Unrecognised VCard version: " . $this->version);
				return;
		}
		
		// Private contact ?
		if (isset($contactProperties[$p['private']]) && $contactProperties[$p['private']]) {
			$vCard->add('CLASS', 'PRIVATE');		// Not in VCARD 4.0 but keep it for compatibility
		}

		// Mandatory FN
		$this->setVCard($vCard, 'FN', $contactProperties, $p['display_name']);
		
		// Contact name and pro information
		// N property
		/*
		   Special note:  The structured property value corresponds, in
			  sequence, to the Family Names (also known as surnames), Given
			  Names, Additional Names, Honorific Prefixes, and Honorific
			  Suffixes.  The text components are separated by the SEMICOLON
			  character (U+003B).  Individual text components can include
			  multiple text values separated by the COMMA character (U+002C).
			  This property is based on the semantics of the X.520 individual
			  name attributes [CCITT.X520.1988].  The property SHOULD be present
			  in the vCard object when the name of the object the vCard
			  represents follows the X.520 model.

			  The SORT-AS parameter MAY be applied to this property.
		*/		
		
		$contactInfos = array();
		$contactInfos[] = isset($contactProperties[$p['surname']])             ? $contactProperties[$p['surname']] : '';
		$contactInfos[] = isset($contactProperties[$p['given_name']])          ? $contactProperties[$p['given_name']] : '';
		$contactInfos[] = isset($contactProperties[$p['middle_name']])         ? $contactProperties[$p['middle_name']] : '';
		$contactInfos[] = isset($contactProperties[$p['display_name_prefix']]) ? $contactProperties[$p['display_name_prefix']] : '';
		$contactInfos[] = isset($contactProperties[$p['generation']])          ? $contactProperties[$p['generation']] : '';

		$element = new Sabre\VObject\Property("N");
		$element->setValue(implode(';', $contactInfos));
		// $element->offsetSet("SORT-AS", '"' . $contactProperties[$p['fileas']] . '"');
		$vCard->add($element);

		// Add ORG:<company>;<department>
		$orgdata = array();
		$orgdata[] = (isset($contactProperties[$p['company_name']])) ? $contactProperties[$p['company_name']] : '';
		$orgdata[] = (isset($contactProperties[$p['department_name']])) ? $contactProperties[$p['department_name']] : '';
		$element = new Sabre\VObject\Property('ORG');
		$element->setValue(implode(';', $orgdata));
		$vCard->add($element);

		$this->setVCard($vCard, 'SORT-AS',         $contactProperties, $p['fileas']);
		$this->setVCard($vCard, 'NICKNAME',        $contactProperties, $p['nickname']);
		$this->setVCard($vCard, 'TITLE',           $contactProperties, $p['title']);
		$this->setVCard($vCard, 'ROLE',            $contactProperties, $p['profession']);
		$this->setVCard($vCard, 'OFFICE',          $contactProperties, $p['office_location']);

		if ($this->version >= 4) {
			if (isset($contactProperties[$p['assistant']])) {
				if (!empty ($contactProperties[$p['assistant']])) {
					$element = new Sabre\VObject\Property('RELATED');
					$element->setValue( $contactProperties[$p['assistant']]);
					$element->offsetSet('TYPE','assistant');	// Not RFC compliant
					$vCard->add($element);
				}
			}

			if (isset($contactProperties[$p['manager_name']])) {
				if (!empty ($contactProperties[$p['manager_name']])) {
					$element = new Sabre\VObject\Property('RELATED');
					$element->setValue( $contactProperties[$p['manager_name']]);
					$element->offsetSet('TYPE','manager');		// Not RFC compliant
					$vCard->add($element);
				}
			}

			if (isset($contactProperties[$p['spouse_name']])) {
				if (!empty ($contactProperties[$p['spouse_name']])) {
					$element = new Sabre\VObject\Property('RELATED');
					$element->setValue( $contactProperties[$p['spouse_name']]);
					$element->offsetSet('TYPE','spouse');
					$vCard->add($element);
				}
			}
		} 
		
		// older syntax - may be needed by some clients so keep it!
		$map = array
			( 'X-MS-ASSISTANT' => 'assistant'
			, 'X-MS-MANAGER'   => 'manager_name'
			, 'X-MS-SPOUSE'    => 'spouse_name'
			);
		foreach ($map as $prop_vcard => $prop_mapi) {
			$this->setVCard($vCard, $prop_vcard, $contactProperties, $p[$prop_mapi]);
		}

		// Dates
		if (isset($contactProperties[$p['birthday']])) {
			$vCard->add('BDAY', date(DATE_PATTERN, $contactProperties[$p['birthday']]));
		}
		if (isset($contactProperties[$p['wedding_anniversary']])) {
			if ($this->version >= 4) {
				$vCard->add('ANNIVERSARY', date(DATE_PATTERN, $contactProperties[$p['wedding_anniversary']]));
			}
			else {
				$vCard->add('X-ANNIVERSARY', date(DATE_PATTERN, $contactProperties[$p['wedding_anniversary']]));
			}
		}
		
		// Telephone numbers
		// webaccess can handle 19 telephone numbers...
		$map = array
			( 'home_telephone_number'      => array('type' => array('HOME','VOICE'), 'pref' => '1')
			, 'home2_telephone_number'     => array('type' => array('HOME','VOICE'), 'pref' => '2')
			, 'office_telephone_number'    => array('type' => array('WORK','VOICE'), 'pref' => '1')
			, 'business2_telephone_number' => array('type' => array('WORK','VOICE'), 'pref' => '2')
			, 'business_fax_number'        => array('type' => array('WORK','FAX'))
			, 'home_fax_number'            => array('type' => array('HOME','FAX'))
			, 'cellular_telephone_number'  => array('type' => 'CELL')
			, 'mobile_telephone_number'    => array('type' => 'CELL')
			, 'pager_telephone_number'     => array('type' => 'PAGER')
			, 'isdn_number'                => array('type' => 'ISDN')
			, 'company_telephone_number'   => array('type' => 'WORK')
			, 'car_telephone_number'       => array('type' => 'CAR')
			, 'assistant_telephone_number' => array('type' => 'SECR')
			, 'other_telephone_number'     => array('type' => 'OTHER')
			, 'primary_telephone_number'   => array('type' => 'VOICE', 'pref' => '1')
			, 'primary_fax_number'         => array('type' => 'FAX', 'pref' => '1')
			, 'ttytdd_telephone_number'    => array('type' => 'TEXTPHONE')
			);

		// OSX Addressbook sends back VCards in this format:
		// TEL;type=WORK;type=VOICE:00334xxxxx
		foreach ($map as $prop_mapi => $prop_vcard) {
			if (!isset($contactProperties[$p[$prop_mapi]]) || $contactProperties[$p[$prop_mapi]] == '') {
				continue;
			}
			$vCard->add('TEL', $contactProperties[$p[$prop_mapi]], $prop_vcard);
		}
		// There are unmatched telephone numbers in zarafa, use them!
		$unmatchedProperties = array
			( 'callback_telephone_number'
			, 'radio_telephone_number'
			, 'telex_telephone_number'
			);
		if (in_array(DEFAULT_TELEPHONE_NUMBER_PROPERTY, $unmatchedProperties)) {
			// unmatched found a match!
			$this->setVCard($vCard, 'TEL', $contactProperties, $p[DEFAULT_TELEPHONE_NUMBER_PROPERTY]);
		}

		$this->setVCardAddress($vCard, 'HOME',  $contactProperties, 'home');
		$this->setVCardAddress($vCard, 'WORK',  $contactProperties, 'business');
		$this->setVCardAddress($vCard, 'OTHER', $contactProperties, 'other');
		
		// emails
		for ($i = 1; $i <= 3; $i++) {
			if (isset($contactProperties[$p["email_address_$i"]])) {
				// Zarafa needs an email display name
				$emailProperty = new Sabre\VObject\Property('EMAIL', $contactProperties[$p["email_address_$i"]]);
				
				// Get display name
				$dn = isset($contactProperties[$p["email_address_display_name_$i"]])
				          ? $contactProperties[$p["email_address_display_name_$i"]]
				          : $contactProperties[$p['display_name']];

				$emailProperty->offsetSet("X-CN", '"' . $dn . '"');
				$vCard->add($emailProperty);
			}
		}
		
		// URL and Instant Messenging (vCard 3.0 extension)
		$this->setVCard($vCard,'URL',   $contactProperties,$p["webpage"]); 
		$this->setVCard($vCard,'IMPP',  $contactProperties,$p["im"]); 
		
		// Categories
		$contactCategories = '';
		if (isset($contactProperties[$p['categories']])) {
			if (is_array($contactProperties[$p['categories']])) {
				$contactCategories = implode(',', $contactProperties[$p['categories']]);
			} else {
				$contactCategories = $contactProperties[$p['categories']];
			}
		}
		if ($contactCategories != '') {
			$vCard->add('CATEGORIES',  $contactCategories);
		}

		// Contact picture?
		$this->get_contact_picture($vCard, $contact, $contactProperties);

		// Misc
		if (!isset($contactProperties[PR_CARDDAV_URI])) {
			// Create an URI from the EntryID:
			$contactProperties[PR_CARDDAV_URI] = $this->bridge->entryid_to_uri($contactProperties[PR_ENTRYID]);
		}
		$vCard->add('UID', "urn:uuid:" . substr($contactProperties[PR_CARDDAV_URI], 0, -4)); // $this->entryIdToStr($contactProperties[PR_ENTRYID]));
		$this->setVCard($vCard, 'NOTE', $contactProperties, $p['notes']);
		$vCard->add('PRODID', VCARD_PRODUCT_ID);
		$vCard->add('REV', date('c',$contactProperties[$p['last_modification_time']]));
	}

	private function
	get_contact_picture (&$vCard, $contact, $props)
	{
		if (!isset($props[PR_HASATTACH]) || !$props[PR_HASATTACH]) {
			return;
		}
		if (FALSE($attachment_table = mapi_message_getattachmenttable($contact))
		 || FALSE($attachments = mapi_table_queryallrows($attachment_table, array
			( PR_ATTACH_NUM
			, PR_ATTACH_SIZE
			, PR_ATTACH_LONG_FILENAME
			, PR_ATTACH_FILENAME
			, PR_ATTACHMENT_HIDDEN
			, PR_DISPLAY_NAME
			, PR_ATTACH_METHOD
			, PR_ATTACH_CONTENT_ID
			, PR_ATTACH_MIME_TAG
			, PR_ATTACHMENT_CONTACTPHOTO
			, PR_EC_WA_ATTACHMENT_HIDDEN_OVERRIDE
			)))) {
			return;
		}
		$photo = FALSE;
		foreach ($attachments as $attachment) {
			if (!isset($attachment[PR_ATTACHMENT_CONTACTPHOTO]) || !$attachment[PR_ATTACHMENT_CONTACTPHOTO]) {
				continue;
			}
			if (FALSE($handle = mapi_message_openattach($contact, $attachment[PR_ATTACH_NUM]))
			 || FALSE($photo = mapi_attach_openbin($handle, PR_ATTACH_DATA_BIN))) {
				continue;
			}
			$mime = (isset($attachment[PR_ATTACH_MIME_TAG])) ? $attachment[PR_ATTACH_MIME_TAG] : 'image/jpeg';
			break;
		}
		if (FALSE($photo)) {
			return;
		}
		// SogoConnector does not like image/jpeg
		if ($mime == 'image/jpeg') {
			$mime = 'JPEG';
		}
		$this->logger->trace("Adding contact picture to VCard");
		$photoProperty = new Sabre\VObject\Property('PHOTO', base64_encode($photo));
		$photoProperty->offsetSet('TYPE', $mime);
		$photoProperty->offsetSet('ENCODING', 'b');
		$vCard->add($photoProperty);
	}

	/**
	 * Helper function to set a vObject property
	 */
	protected function setVCard($vCard, $vCardProperty, &$contactProperties, $propertyId) {
		if (isset($contactProperties[$propertyId]) && ($contactProperties[$propertyId] != '')) {
			$vCard->add($vCardProperty, $contactProperties[$propertyId]);
		}
	}

	/**
	 * Helper function to set an address in vObject
	 */
	protected function setVCardAddress($vCard, $addressType, &$contactProperties, $propertyPrefix) {

		$this->logger->trace("setVCardAddress - $addressType");
		
		$p = $this->bridge->getExtendedProperties();
		
		$address = array();
		if (isset($p["{$propertyPrefix}_address"])) {
			$address[] = '';	// post office box
			$address[] = '';	// extended address
			$address[] = isset($contactProperties[$p[$propertyPrefix . '_address_street']])      ? $contactProperties[$p[$propertyPrefix . '_address_street']] : '';
			$address[] = isset($contactProperties[$p[$propertyPrefix . '_address_city']])        ? $contactProperties[$p[$propertyPrefix . '_address_city']] : '';
			$address[] = isset($contactProperties[$p[$propertyPrefix . '_address_state']])       ? $contactProperties[$p[$propertyPrefix . '_address_state']] : '';
			$address[] = isset($contactProperties[$p[$propertyPrefix . '_address_postal_code']]) ? $contactProperties[$p[$propertyPrefix . '_address_postal_code']] : '';
			$address[] = isset($contactProperties[$p[$propertyPrefix . '_address_country']])     ? $contactProperties[$p[$propertyPrefix . '_address_country']] : '';
		}
		
		$address = implode(';', $address);
		
		if ($address != ';;;;;;') {
			$this->logger->trace("Not empty address - adding $address");
			$element = new Sabre\VObject\Property('ADR');
			$element->setValue($address);
			$element->offsetSet('TYPE', $addressType);
			$vCard->add($element);
		}
	}

}
