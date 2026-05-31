<?php
/**
 * LLM Connector interface and implementations
 *
 * @package Prompt_To_Page
 */

/**
 * Interface for LLM connectors
 */
interface LLM_Connector {
	/**
	 * Send a prompt to the LLM
	 *
	 * @param array $messages The messages to send.
	 * @return WP_Error|string
	 */
	public function send_prompt( array $messages );
}

/**
 * Abstract base class for LLM connectors
 */
abstract class Abstract_LLM_Connector implements LLM_Connector {
	/**
	 * API key for the LLM service
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * Constructor
	 *
	 * @param string $api_key The API key.
	 */
	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}
}

/**
 * OpenRouter connector implementation
 */
class OpenRouter_Connector extends Abstract_LLM_Connector {
	/**
	 * Send a prompt to the OpenRouter LLM
	 *
	 * @param array $messages The messages to send.
	 * @return WP_Error|string
	 */
	public function send_prompt( array $messages ) {
		// Prepare the request body
		$body = wp_json_encode(
			array(
				'model'   => 'anthropic/claude-4-sonnet',
				'messages' => $messages,
			)
		);

		// Set up the request arguments
		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => $body,
			'timeout' => 30, // 30 second timeout
		);

		// Send the request
		$response = wp_remote_post(
			'https://openrouter.ai/api/v1/chat/completions',
			$args
		);

		// Check for HTTP errors
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Check for HTTP error status codes
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code >= 300 ) {
			return new WP_Error(
				'http_error',
				sprintf(
					'HTTP error: %d',
					$response_code
				)
			);
		}

		// Get the response body
		$response_body = wp_remote_retrieve_body( $response );
		$response_json = json_decode( $response_body, true );

		// Check if JSON decoding was successful
		if ( null === $response_json ) {
			return new WP_Error(
				'json_decode_error',
				'Failed to decode JSON response'
			);
		}

		// Check if the response contains the expected structure
		if ( ! isset( $response_json['choices'] ) || ! is_array( $response_json['choices'] ) || empty( $response_json['choices'] ) ) {
			return new WP_Error(
				'invalid_response',
				'Invalid response structure from LLM'
			);
		}

		// Return the content from the first choice
		$first_choice = $response_json['choices'][0];
		if ( ! isset( $first_choice['message'] ) || ! isset( $first_choice['message']['content'] ) ) {
			return new WP_Error(
				'invalid_choice',
				'Invalid choice structure in response'
			);
		}

		return $first_choice['message']['content'];
	}
}