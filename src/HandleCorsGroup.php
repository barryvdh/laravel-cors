<?php

namespace Fruitcake\Cors;

use Illuminate\Http\Request;

class HandleCorsGroup extends HandleCors
{
    /**
     * Check if it's an CORS request, skip the paths check
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function shouldRun(Request $request): bool
    {
        // Check if this is an actual CORS request
        return $this->cors->isCorsRequest($request);
    }
}
