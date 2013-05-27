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
 
require_once "vcard/IVCardParser.php";
require_once "config.inc.php";

require_once 'ZarafaLogger.php';

// PHP-MAPI
require_once("mapi/mapi.util.php");
require_once("mapi/mapicode.php");
require_once("mapi/mapidefs.php");
require_once("mapi/mapitags.php");
require_once("mapi/mapiguid.php");
	
class VCardParser implements IVCardParser
{
	protected $bridge;
	protected $logger;
	protected $vcard = FALSE;
	protected $mapi = array();
	protected $extendedProperties = FALSE;

	function __construct ($bridge)
	{
		$this->bridge = $bridge;
		$this->logger = new Zarafa_Logger(__CLASS__);
		$this->logger->trace(__CLASS__ . " constructor done.");
	}

	/**
	 * Convert vObject to an array of properties
	 * @param string $vcardData the vCard in string form
	 */
	public function vObjectToProperties ($vcardData)
	{
		$this->logger->trace(__FUNCTION__);

		$this->vcard = Sabre\VObject\Reader::read($vcardData);

		if (FALSE($this->vcard)) {
			$this->logger->fatal('failed to create vCard object');
			return FALSE;
		}
		$this->logger->trace("VObject: \n" . print_r($this->vcard, TRUE));
		
		// Common VCard properties parsing
		if (FALSE($this->extendedProperties = $this->bridge->getExtendedProperties())) {
			$this->logger->fatal('failed to load extended properties');
			return FALSE;
		}
		// Use shorthand notation for brevity's sake:
		$p = $this->extendedProperties;

		// Init properties
		if (CLEAR_MISSING_PROPERTIES) {
			$this->logger->trace("Clearing missing properties");
			$this->mapi[$p['surname']] = NULL;
			$this->mapi[$p['given_name']] = NULL;
			$this->mapi[$p['middle_name']] = NULL;
			$this->mapi[$p['display_name_prefix']] = NULL;
			$this->mapi[$p['generation']] = NULL;
			$this->mapi[$p['display_name']] = NULL;
			$this->mapi[$p['nickname']] = NULL;
			$this->mapi[$p['title']] = NULL;
			$this->mapi[$p['profession']] = NULL;
			$this->mapi[$p['office_location']] = NULL;
			$this->mapi[$p['company_name']] = NULL;
			$this->mapi[$p['department_name']] = NULL;
			$this->mapi[$p['birthday']] = NULL;
			$this->mapi[$p['wedding_anniversary']] = NULL;
			$this->mapi[$p['home_telephone_number']] = NULL;
			$this->mapi[$p['home2_telephone_number']] = NULL;
			$this->mapi[$p['cellular_telephone_number']] = NULL;
			$this->mapi[$p['office_telephone_number']] = NULL;
			$this->mapi[$p['business2_telephone_number']] = NULL;
			$this->mapi[$p['business_fax_number']] = NULL;
			$this->mapi[$p['home_fax_number']] = NULL;
			$this->mapi[$p['primary_fax_number']] = NULL;
			$this->mapi[$p['primary_telephone_number']] = NULL;
			$this->mapi[$p['pager_telephone_number']] = NULL;
			$this->mapi[$p['other_telephone_number']] = NULL;
			$this->mapi[$p['isdn_number']] = NULL;
			$this->mapi[$p['company_telephone_number']] = NULL;
			$this->mapi[$p['car_telephone_number']] = NULL;
			$this->mapi[$p['assistant_telephone_number']] = NULL;
			$this->mapi[$p['assistant']] = NULL;
			$this->mapi[$p['manager_name']] = NULL;
			$this->mapi[$p['mobile_telephone_number']] = NULL;
			$this->mapi[$p['ttytdd_telephone_number']] = NULL;
			$this->mapi[$p['spouse_name']] = NULL;
			$this->mapi[$p['home_address_street']] = NULL;
			$this->mapi[$p['home_address_city']] = NULL;
			$this->mapi[$p['home_address_state']] = NULL;
			$this->mapi[$p['home_address_postal_code']] = NULL;
			$this->mapi[$p['home_address_country']] = NULL;
			$this->mapi[$p['business_address_street']] = NULL;
			$this->mapi[$p['business_address_city']] = NULL;
			$this->mapi[$p['business_address_state']] = NULL;
			$this->mapi[$p['business_address_postal_code']] = NULL;
			$this->mapi[$p['business_address_country']] = NULL;
			$this->mapi[$p['other_address_street']] = NULL;
			$this->mapi[$p['other_address_city']] = NULL;
			$this->mapi[$p['other_address_state']] = NULL;
			$this->mapi[$p['other_address_postal_code']] = NULL;
			$this->mapi[$p['other_address_country']] = NULL;
			$nremails = array();
			$abprovidertype = 0;
			for ($i = 1; $i <= 3; $i++) {
				$this->mapi[$p["email_address_$i"]] = NULL;
				$this->mapi[$p["email_address_display_name_email_$i"]] = NULL;
				$this->mapi[$p["email_address_display_name_$i"]] = NULL;
				$this->mapi[$p["email_address_type_$i"]] = NULL;
				$this->mapi[$p["email_address_entryid_$i"]] = NULL;
			}
			$this->mapi[$p["address_book_mv"]] = NULL;
			$this->mapi[$p["address_book_long"]] = NULL;
			$this->mapi[$p['webpage']] = NULL;
			$this->mapi[$p['im']] = NULL;
			$this->mapi[$p['categories']] = NULL;
			$this->mapi['ContactPicture'] = NULL;
			$this->mapi[PR_HASATTACH] = false;
			$this->mapi[$p['has_picture']] = false;
		}
		
		// Name components
		$sortAs = '';
		if (isset($this->vcard->N)) {
			$this->logger->trace("N: " . $this->vcard->N);
			$parts = $this->vcard->N->getParts();

			$dump = print_r($parts, true);
			$this->logger->trace("Name info\n$dump");
			
			$this->mapi[$p['surname']]             = isset($parts[0]) ? $parts[0] : '';
			$this->mapi[$p['given_name']]          = isset($parts[1]) ? $parts[1] : '';
			$this->mapi[$p['middle_name']]         = isset($parts[2]) ? $parts[2] : '';
			$this->mapi[$p['display_name_prefix']] = isset($parts[3]) ? $parts[3] : '';
			$this->mapi[$p['generation']]          = isset($parts[4]) ? $parts[4] : '';
			
			// Issue 3#8
			if ($this->vcard->n->offsetExists('SORT-AS')) {
				$sortAs = $this->vcard->n->offsetGet('SORT-AS')->value;
			}
		}
		
		// Given sort-as ?
		/*
		if (isset($this->vcard->sort-as)) {
			$this->logger->debug("Using vcard SORT-AS");
			$sortAs = $this->vcard->sort-as->value;
		}
		*/
		$sortAsProperty = $this->vcard->select('SORT-AS');
		if (count($sortAsProperty) != 0) {
			$sortAs = current($sortAsProperty)->value;
		}

		// Some VCard properties can be mapped 1:1 to MAPI properties:
		$map = array
			( 'NICKNAME'       => 'nickname'
			, 'TITLE'          => 'title'
			, 'ROLE'           => 'profession'
			, 'OFFICE'         => 'office_location'
			, 'NOTE'           => 'notes'
			, 'X-MS-ASSISTANT' => 'assistant'
			, 'X-MS-MANAGER'   => 'manager_name'
			, 'X-MS-SPOUSE'    => 'spouse_name'

			// TODO: treat these as multivalues?
			// MAPI has no support for more than one website or IM account...
			, 'URL'            => 'webpage'
			, 'IMPP'           => 'im'
			);

		// Use a 'foreach' because each property can exist zero, one or more times,
		// and because $this->vcard->select() returns an array:
		foreach ($map as $prop_vcard => $prop_mapi)
		{
			// If a property occurs more than once, we take the *first*
			// mention to be the most important:
			$already_set = FALSE;
			foreach ($this->vcard->select($prop_vcard) as $prop) {
				if ($already_set) {
					$this->logger->info("Discarding $prop_vcard with value '{$prop->value}'; MAPI can store just one field.");
					continue;
				}
				$this->mapi[$p[$prop_mapi]] = $prop->value;
				$already_set = TRUE;
			}
		}
		if (isset($this->vcard->ORG)) {
			$parts = $this->vcard->ORG->getParts();
			if (isset($parts[0])) $this->mapi[$p['company_name']] = $parts[0];
			if (isset($parts[1])) $this->mapi[$p['department_name']] = $parts[1];
		}
		if (isset($this->vcard->FN)) {
			$this->mapi[$p['display_name']] = $this->vcard->FN->value;
			$this->mapi[PR_SUBJECT] = $this->vcard->FN->value;
		}
		if (empty($sortAs) || SAVE_AS_OVERRIDE_SORTAS) {
			$this->logger->trace("Empty sort-as or SAVE_AS_OVERRIDE_SORTAS set");
			$sortAs = SAVE_AS_PATTERN;		// $this->vcard->fn->value;
			
			// Do substitutions
			$substitutionKeys   = array('%d', '%l', '%f', '%c');
			$substitutionValues = array(
				$this->mapi[$p['display_name']],
				$this->mapi[$p['surname']],
				$this->mapi[$p['given_name']],
				$this->mapi[$p['company_name']]
			);
			$sortAs = str_replace($substitutionKeys, $substitutionValues, $sortAs);
		}

		// Should PR_SUBJET and display_name be equals to fileas? I think so!
		$this->logger->debug("Contact display name: " . $sortAs);
		$this->mapi[$p['fileas']] = $sortAs;
		$this->mapi[$p['display_name']] = $sortAs;
		$this->mapi[PR_SUBJECT] = $sortAs;
		
		// Dates:
		if (isset($this->vcard->bday)) {
			$time = new DateTime($this->vcard->bday->value);
			$this->mapi[$p['birthday']] = $time->format('U');
		}
		if (isset($this->vcard->anniversary)) {
			$time = new DateTime($this->vcard->anniversary->value);
			$this->mapi[$p['wedding_anniversary']] = $time->format('U');
		}
		if (isset($this->vcard->rev)) {
			$time = new DateTime($this->vcard->rev->value);
			$this->mapi[$p['last_modification_time']] = $time->format('U');
		}
		else {
			$this->mapi[$p['last_modification_time']] = time();
		}
		// Telephone numbers
		$this->phoneConvert();

		// RELATED fields:
		$this->relatedConvert();

		// Social media profiles:
		$this->socialProfileConvert();

		// Addresses...
		$this->addressConvert();
		
		// emails need to handle complementary properties plus create one off entries!
		$nremails = array();
		$abprovidertype = 0;
		$emails = $this->vcard->select('EMAIL');
		$emailsDisplayName = $this->vcard->select('X-EMAIL-CN');		// emClient handles those
		$numMail = 0;
		
		if (is_array($emailsDisplayName)) {
			$emailsDisplayName = array_values($emailsDisplayName);
		}
		
		$dump = print_r($emailsDisplayName, true);
		$this->logger->trace("Display Names\n$dump");
		
		foreach ($emails as $email) {
			$numMail++;
			$displayName = '';
			
			if ($numMail > 3) {
				// Zarafa only handles 3 mails
				break;
			}
			
			$address = $email->value;
			
			if (count($emailsDisplayName) >= $numMail) {
				// Display name exists, use it!
				$displayName = $emailsDisplayName[$numMail - 1]->value;
			} else {
				$displayName = $this->vcard->fn->value;
			}
			
			// Override displayName?
			if ($email->offsetExists("X-CN")) {
				$xCn = $email->offsetGet("X-CN");
				$displayName = $xCn->value;
			}
			
			$this->logger->debug("Found email $numMail : $displayName <$address>");
			
			$this->mapi[$p["email_address_$numMail"]] = $address;
			$this->mapi[$p["email_address_display_name_email_$numMail"]] = $address;
			$this->mapi[$p["email_address_display_name_$numMail"]] = $displayName;
			$this->mapi[$p["email_address_type_$numMail"]] = "SMTP";
			$this->mapi[$p["email_address_entryid_$numMail"]] = mapi_createoneoff($displayName, "SMTP", $address);
			$nremails[] = $numMail - 1;
			$abprovidertype |= 2 ^ ($numMail - 1);
		}
		
		if ($numMail > 0) {
			if (!empty($nremails)) $this->mapi[$p["address_book_mv"]] = $nremails;
			$this->mapi[$p["address_book_long"]] = $abprovidertype;
		}
		
		// Categories (multi values)
		if (isset($this->vcard->categories)) 		$this->mapi[$p['categories']] = explode(',', $this->vcard->categories->value);
		
		// Contact picture
		if (isset($this->vcard->photo)) {
			$type     = strtolower($this->vcard->photo->offsetGet('TYPE')->value);
			$encoding = strtolower($this->vcard->photo->offsetGet('ENCODING')->value);
			$content  = $this->vcard->photo->value;

			$this->logger->debug("Found contact picture type $type encoding $encoding");

			$this->photoConvert($content, $type, $encoding);
		}
		
		// Misc
		$this->mapi[$p["icon_index"]] = "512";		// Zarafa specific?

		return $this->mapi;
	}

	private function
	addressConvert ()
	{
		$p = $this->extendedProperties;

		$map = array
			( 'HOME'  => 'home'
			, 'WORK'  => 'business'
			, 'OTHER' => 'other'
			);

		foreach ($this->vcard->select('ADR') as $addr) {
			if (($type = $addr->offsetGet('TYPE')) === NULL) {
			// TODO: These properties are the so-called mailing address. This address
			// appears, in Zarafa, to always be linked to one of the home/business/other
			// types (it's a checkbox you can set on any one of them). Until we do
			// further research, don't write any unique values to these fields:
			//	$pStreet  = 'street';
			//	$pCity    = 'city';
			//	$pState   = 'state';
			//	$pPCode   = 'postal_code';
			//	$pCountry = 'country';
				$this->logger->info('Ignoring address without type parameter');
				continue;
			}
			else {
				$type = strtoupper($type->value);
				if (!isset($map[$type])) {
					$this->logger->info("Ignoring address with unknown type '$type'");
					continue;
				}
				$this->logger->debug("Found address '$type', mapping to '{$map[$type]}'");

				$pStreet  = "{$map[$type]}_address_street";
				$pCity    = "{$map[$type]}_address_city";
				$pState   = "{$map[$type]}_address_state";
				$pPCode   = "{$map[$type]}_address_postal_code";
				$pCountry = "{$map[$type]}_address_country";
			}
			$parts = $addr->getParts();
			$this->logger->trace("Address components:\n".print_r($parts, TRUE));

			$this->mapi[$p[$pStreet]]  = isset($parts[2]) ? $parts[2] : '';
			$this->mapi[$p[$pCity]]    = isset($parts[3]) ? $parts[3] : '';
			$this->mapi[$p[$pState]]   = isset($parts[4]) ? $parts[4] : '';
			$this->mapi[$p[$pPCode]]   = isset($parts[5]) ? $parts[5] : '';
			$this->mapi[$p[$pCountry]] = isset($parts[6]) ? $parts[6] : '';
		}
	}

	private function
	phoneConvert ()
	{
		$n_home_voice = 0;
		$n_work_voice = 0;
		foreach ($this->vcard->select('TEL') as $tel)
		{
			$pk = FALSE;
			$types = array();

			// Get array of types; $type is a Sabre\VObject\Parameter:
			foreach ($tel->offsetGet('TYPE') as $type) {
				$types[strtoupper($type->value)] = TRUE;
			}
			if (isset($types['HOME'])) {
				if (isset($types['FAX'])) {
					$pk = 'home_fax_number';
				}
				else {
					if (($pref = $tel->offsetGet('PREF')) !== NULL) {
						$pk = ($pref->value == '1')
						    ? 'home_telephone_number'
						    : 'home2_telephone_number';
					}
					else {
						$pk = ($n_home_voice == 1)
						    ? 'home2_telephone_number'
						    : 'home_telephone_number';
					}
					$n_home_voice++;
				}
			}
			elseif (isset($types['WORK'])) {
				if (isset($types['VOICE'])) {
					if (($pref = $tel->offsetGet('PREF')) !== NULL) {
						$pk = ($pref->value == '1')
						    ? 'office_telephone_number'
						    : 'business2_telephone_number';
					}
					else {
						$pk = ($n_work_voice == 1)
						    ? 'business2_telephone_number'
						    : 'office_telephone_number';
					}
					$n_work_voice++;
				}
				elseif (isset($types['FAX'])) {
					$pk = 'business_fax_number';
				}
				else $pk = 'company_telephone_number';
			}
			elseif (isset($types['OTHER']))
			{
				// There is unfortunately no 'other_fax_number'.
				// TODO: Zarafa defines faxes 1..3, maybe use them here:
				if (!isset($types['FAX'])) {
					$pk = 'other_telephone_number';
				}
			}
			if (FALSE($pk)) {
				// No match yet? Try to match against map:
				// Note: there is also 'cellular_telephone_number',
				// but it's an alias for 'mobile_telephone_number'.
				$map = array
					( 'CAR'       => 'car_telephone_number'
					, 'CELL'      => 'mobile_telephone_number'
					, 'FAX'       => 'primary_fax_number'
					, 'IPHONE'    => 'mobile_telephone_number'
					, 'ISDN'      => 'isdn_number'
					, 'MAIN'      => 'primary_telephone_number'
					, 'PAGER'     => 'pager_telephone_number'
					, 'SECR'      => 'assistant_telephone_number'
					, 'TEXTPHONE' => 'ttytdd_telephone_number'
					);

				foreach ($map as $prop_vcard => $prop_mapi) {
					if (isset($types[$prop_vcard])) {
						$pk = $prop_mapi;
						break;
					}
				}
			}
			// Still no match found?
			if (FALSE($pk)) {
				// If no type info set (so just 'TEL:'), use default phone property:
				if (count($types) == 0) {
					$pk = DEFAULT_TELEPHONE_NUMBER_PROPERTY;
				}
				// Otherwise some unknown type was specified:
				else {
					$this->logger->warn('Unknown telephone type(s): '.implode(';', array_keys($types)));
					continue;
				}
			}
			$this->mapi[$this->extendedProperties[$pk]] = $tel->value;
		}
	}

	private function
	photoConvert ($content, $type, $encoding)
	{
		if ($encoding !== 'b' && $encoding != '') {
			$this->logger->warn("Encoding not supported: $encoding");
			return FALSE;
		}
		if (FALSE($content = base64_decode($content))) {
			$this->logger->warn('Error: failed to base64-decode contact photo');
			return FALSE;
		}
		// Convert to JPEG if not already in that format:
		if ($type != 'jpeg' && $type != 'image/jpeg' && $type != 'image/jpg')
		{
			if (FALSE(extension_loaded('gd'))) {
				$this->logger->warn("Cannot convert image of type \"$type\" to jpeg: GD extension not installed");
				return FALSE;
			}
			$this->logger->trace('Converting to jpeg using GD');
			if (FALSE($img = imagecreatefromstring($content))) {
				$this->logger->warn('Corrupted contact picture or unknown format');
				return FALSE;
			}
			$this->logger->trace('Image loaded by GD');
			// Capture output
			ob_start();
			$r = imagejpeg($img);
			$content = ob_get_contents();
			ob_end_clean();
			imagedestroy($img);
		}
		$this->logger->info('Contact has picture!');
		$this->mapi['ContactPicture'] = $content;
		$this->mapi[PR_HASATTACH] = TRUE;
		$this->mapi[$this->extendedProperties['has_picture']] = TRUE;

		return TRUE;
	}

	private function
	relatedConvert ()
	{
		foreach ($this->vcard->select('RELATED') as $prop)
		{
			if (($type = $prop->offsetGet('TYPE')) === NULL) {
				$this->logger->info("Ignoring RELATED property without TYPE parameter '$prop->value'");
				continue;
			}
			switch (strtoupper($type->value)) {
				case 'ASSISTANT': $pk = 'assistant'; break;
				case 'MANAGER': $pk = 'manager_name'; break;
				case 'SPOUSE': $pk = 'spouse_name'; break;
				default: $pk = FALSE;
			}
			if (FALSE($pk)) {
				$this->logger->info("Ignoring RELATED property with unknown TYPE '{$type->value}'");
				continue;
			}
			$this->mapi[$this->extendedProperties[$pk]] = $prop->value;
                }
	}

	private function
	socialProfileConvert ()
	{
		foreach ($this->vcard->select('X-SOCIALPROFILE') as $prop)
		{
			if (($params = $prop->offsetGet('TYPE')) === NULL) {
				$this->logger->trace(sprintf('Ignoring social profile with value "%s"', $prop->value));
				continue;
			}
			$types = array();
			foreach ($params as $param) {
				$types[$param->value] = TRUE;
			}
			// Possibly do something with the types and objects here.
			// Observed strings passed by OSX Contacts:
			//   X-SOCIALPROFILE;type=twitter:http://twitter.com/name
			//   X-SOCIALPROFILE;type=facebook:http://facebook.com/name
			//   X-SOCIALPROFILE;type=flickr:http://www.flickr.com/photos/name
			//   X-SOCIALPROFILE;type=linkedin:http://www.linkedin.com/in/name
			//   X-SOCIALPROFILE;type=myspace:http://www.myspace.com/name
			//   X-SOCIALPROFILE;type=sinaweibo:http://weibo.com/n/name

			$this->logger->trace(sprintf('Ignoring social profile at "%s" with value "%s"', implode('/', array_keys($types)), $prop->value));
		}
	}
}
