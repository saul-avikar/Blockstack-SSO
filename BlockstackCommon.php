<?php
/**
 * This class intergrates blockstack with php.
 * Author: Saul Boyd (avikar.io)
 * License: GPL (http://www.gnu.org/copyleft/gpl.html)
*/

class BlockstackCommon {
	public function __construct() {}

	// this function is to be called to verify and obtain the blockstack data
	public function auth() {
		$user = file_get_contents( 'php://input' );

		if ( !isset( $user ) || $user === "" ) {
			return $this->respond(true, "invalid post parameters");
		}

		$userData = json_decode( $user, true );

		if ( json_last_error() != JSON_ERROR_NONE ) {
			return $this->respond(true, "invalid json");
		}

		if ( !isset( $userData["appPrivateKey"]) || strlen( $userData["appPrivateKey"]) < 32 ) {
			return $this->respond( true, "invalid key" );
		}

		if ( !isset( $userData["did"] ) ) {
			return $this->respond( true, "missing did" );
		}

		// check to see if we have failed to get the name and try for the hosted profile data
		// this is to fix a bug in the browser version not returning any profile data
		if ( !isset( $userData["profile"]["name"] ) ) {
			$profileData = $this->getProfileFromDid( $userData["did"] );

			if ( $profileData ) {
				$userData["profile"] = $profileData;
			}
		}

		if ( !isset( $userData["profile"]["image"][0]["contentUrl"] ) ) {
			$userData["avatarUrl"] = "https://s3.amazonaws.com/onename/avatar-placeholder.png";
		}
		else {
			$userData["avatarUrl"] = $userData["profile"]["image"][0]["contentUrl"];
		}

		if ( !isset($userData["profile"]["name"] ) ) {
			$userData["profile"]["name"] = "Anonymous";
		}

		if ( !isset($userData["profile"]["description"] ) ) {
			$userData["profile"]["description"] = "";
		}

		$userData["password"] =  hash_hmac( "sha256", $userData["appPrivateKey"], $userData["appPrivateKey"] );
		$userData["id"] = substr( hash( "sha256", $userData["appPrivateKey"] ), 0, 30 );

		return $this->respond( false, $userData );
	}

	private function respond( $error, $data ) {
		return json_encode(
			array(
				"error" => $error,
				"data" => $data
			)
		);
	}

	// Decodes the token and returns it in an array
	private function decodeToken($token){
		$authParts = explode( '.', $token );

		if ( count($authParts) != 3 ) {
			return false;
		}

		$authParts[0] = json_decode( base64_decode( $authParts[0] ), true );
		$authParts[1] = json_decode( base64_decode( $authParts[1] ), true );

		if ( json_last_error() != JSON_ERROR_NONE ) {
			return false;
		}

		return $authParts;
	}

	private function getProfileFromDid( $did ) {
		$profileDataPage = file_get_contents("https://gaia.blockstack.org/hub/" . $did . "/profile.json");

		if ( !isset( $profileDataPage ) ) {
			return false;
		}

		$profileData = json_decode( $profileDataPage, true );

		if ( json_last_error() != JSON_ERROR_NONE ) {
			return false;
		}

		if ( !isset( $profileData[0]["decodedToken"]["payload"]["claim"] ) ) {
			return false;
		}

		return $profileData[0]["decodedToken"]["payload"]["claim"];
	}
}
