<?php

/*
 * DatabaseVersionTooLow.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2021 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LibreNMS\Interfaces\Exceptions\UpgradeableException;
use LibreNMS\ValidationResult;
use LibreNMS\Validations\Database;
use LibreNMS\Validator;
use Throwable;

class DatabaseInconsistentException extends \Exception implements UpgradeableException
{
    /**
     * @var \LibreNMS\ValidationResult[]
     */
    private $validationResults;

    public function __construct($validationResults, $message = '', $code = 0, ?Throwable $previous = null)
    {
        $this->validationResults = $validationResults;
        parent::__construct($message, $code, $previous);
    }

    public static function upgrade(Throwable $exception): ?static
    {
        if ($exception instanceof QueryException || $exception->getPrevious() instanceof QueryException) {
            try {
                $validator = new Validator();
                (new Database())->validate($validator);

                // get only failed results
                $results = array_filter($validator->getResults('database'), function (ValidationResult $result) {
                    return $result->getStatus() === ValidationResult::FAILURE;
                });

                if ($results) {
                    return new static($results, $exception->getMessage(), 0, $exception);
                }
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }

    /**
     * Render the exception into an HTTP or JSON response.
     */
    public function render(Request $request): Response|JsonResponse
    {
        $message = trans('exceptions.database_inconsistent.title');
        if (isset($this->validationResults[0])) {
            $message .= ': ' . $this->validationResults[0]->getMessage();
        }

        return $request->wantsJson() ? response()->json([
            'status' => 'error',
            'message' => $message,
        ], 500) : response()->view('errors.db_inconsistent', [
            'results' => $this->validationResults,
        ], 500);
    }
}
