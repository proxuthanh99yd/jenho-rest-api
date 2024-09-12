<?php
namespace Okhub\Utils;

class Validator
{

    // Validates that a string is non-empty
    public static function validate_non_empty_string($value, $request, $param)
    {
        if (is_string($value) && !empty(trim($value))) {
            return true;
        }
        return new WP_Error('rest_invalid_param', sprintf('The %s field must be a non-empty string.', $param), ['status' => 400]);
    }

    // Validates an optional string (can be null or a string)
    public static function validate_optional_string($value, $request, $param)
    {
        if (is_null($value) || is_string($value)) {
            return true;
        }
        return new WP_Error('rest_invalid_param', sprintf('The %s field must be a string if provided.', $param), ['status' => 400]);
    }

    // Validates that a phone number matches a basic pattern
    public static function validate_phone_number($value, $request, $param)
    {
        if (is_string($value) && preg_match('/^\+?[0-9\s\-()]{7,}$/', $value)) {
            return true;
        }
        return new WP_Error('rest_invalid_param', sprintf('The %s field must be a valid phone number.', $param), ['status' => 400]);
    }
	
	// Validates an email address
    public static function validate_email($value, $request, $param) {
        if (is_email($value)) {
            return true;
        }
        return new WP_Error('rest_invalid_param', sprintf('The %s field must be a valid email address.', $param), ['status' => 400]);
    }
}