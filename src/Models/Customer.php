<?php

namespace Railroad\CustomerIo\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Customer
 * @package Railroad\CustomerIo\Models
 * @property integer $id
 * @property string $uuid
 * @property string $email
 * @property string $user_id
 * @property string $workspace_name
 * @property string $workspace_id
 * @property string $site_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Customer extends Model
{
    use SoftDeletes;

    protected $table = 'customer_io_customers';
    protected $primaryKey = 'internal_id';

    /**
     * When this hydrated from the API it will be an array of key value pairs.
     *
     * @var bool|array
     */
    private $externalAttributes = false;

    /**
     * Customer constructor.
     */
    public function __construct(array $attributes = [])
    {
        $this->setConnection(config('customer-io.database_connection_name'));

        parent::__construct($attributes);
    }

    public function generateUUID()
    {
        $this->uuid = bin2hex(openssl_random_pseudo_bytes(16));
    }

    /**
     * @return array|bool
     */
    public function getExternalAttributes()
    {
        foreach ($this->externalAttributes as $externalAttributeName => $externalAttributeValue) {
            if ($externalAttributeValue === 'true') {
                $this->externalAttributes[$externalAttributeName] = true;
            }

            if ($externalAttributeValue === 'false') {
                $this->externalAttributes[$externalAttributeName] = false;
            }

            if (is_numeric($externalAttributeValue)) {
                $this->externalAttributes[$externalAttributeName] = (integer)$externalAttributeValue;
            }
        }

        return $this->externalAttributes;
    }

    /**
     * @param  array|bool  $externalAttributes
     */
    public function setExternalAttributes($externalAttributes): void
    {
        $this->externalAttributes = $externalAttributes;
    }
}
