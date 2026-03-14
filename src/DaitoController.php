<?php

namespace Daito\Lib;

use Daito\Lib\Traits\HasDaitoResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Base controller for Laravel projects.
 */
class DaitoController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, HasDaitoResponse, ValidatesRequests;
}
