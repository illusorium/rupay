<?php
namespace Rupay;


use Illuminate\Database\Eloquent\Model as EloquentModel;
use Rupay\Helper\Arr;
use Rupay\Order\Structure;

class Model extends EloquentModel
{
    protected $structure;
    protected $fields;

    public function __construct(array $attributes = [])
    {
        Database::instance();

        $class = last(explode('\\', static::class));
        $structure = Arr::path(
            Structure::get(),
            strtolower($class)
        );

        $this->table = $structure['table'];

        $method = "get{$class}Fields";
        $this->fillable = Structure::$method('public');

        parent::__construct($attributes);
    }
}