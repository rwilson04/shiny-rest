<?php

namespace ShinyRest\Exception;

interface ExceptionInterface
{
	/*
	 * HTTP status code to return, if known
	 * should default to 500
	 */
	public function getHttpStatus();

	/*
	 * Get any additional details
	 *
	 * 'detail' field is the api-problem standard, and should be a string if included 
	 * alternatively, a string can be returned instead of an array
	 * any other array keys will be merged into the root problem array
	 *
	 * @return string|array|null
	 */
	public function getDetails();
}
