<?php

namespace Maklad\Permission\Models;

use Illuminate\Support\Collection;
use Jenssegers\Mongodb\Eloquent\Model;
use Jenssegers\Mongodb\Relations\BelongsToMany;
use Maklad\Permission\Contracts\PermissionInterface;
use Maklad\Permission\Exceptions\PermissionAlreadyExists;
use Maklad\Permission\Exceptions\PermissionDoesNotExist;
use Maklad\Permission\Guard;
use Maklad\Permission\Helpers;
use Maklad\Permission\PermissionRegistrar;
use Maklad\Permission\Traits\HasRoles;
use Maklad\Permission\Traits\RefreshesPermissionCache;

/**
 * Class Permission
 * @package Maklad\Permission\Models
 */
class Permission extends Model implements PermissionInterface
{
    use HasRoles;
    use RefreshesPermissionCache;

    public $guarded = ['id'];
    protected $helpers;

    /**
     * Permission constructor.
     *
     * @param array $attributes
     *
     * @throws \ReflectionException
     */
    public function __construct(array $attributes = [])
    {
        $attributes['guardName'] = $attributes['guardName'] ?? (new Guard())->getDefaultName(static::class);

        parent::__construct($attributes);

        $this->helpers = new Helpers();

        $this->setTable(config('permission.collection_names.permissions'));
    }

    /**
     * Create new Permission
     *
     * @param array $attributes
     *
     * @return $this|\Illuminate\Database\Eloquent\Model
     * @throws \Maklad\Permission\Exceptions\PermissionAlreadyExists
     * @throws \ReflectionException
     */
    public static function create(array $attributes = [])
    {
        $helpers = new Helpers();
        $attributes['guardName'] = $attributes['guardName'] ?? (new Guard())->getDefaultName(static::class);

        if (static::getPermissions()->where('name', $attributes['name'])->where(
            'guardName',
            $attributes['guardName']
        )->first()) {
            $name = (string)$attributes['name'];
            $guardName = (string)$attributes['guardName'];
            throw new PermissionAlreadyExists($helpers->getPermissionAlreadyExistsMessage($name, $guardName));
        }

        return $helpers->checkVersion() ? parent::create($attributes) : static::query()->create($attributes);
    }

    /**
     * Find or create permission by its name (and optionally guardName).
     *
     * @param string $name
     * @param string $guardName
     *
     * @return PermissionInterface
     * @throws \Maklad\Permission\Exceptions\PermissionAlreadyExists
     * @throws \ReflectionException
     */
    public static function findOrCreate(string $name, string $guardName = null): PermissionInterface
    {
        $guardName = $guardName ?? (new Guard())->getDefaultName(static::class);

        $permission = static::getPermissions()->filter(function ($permission) use ($name, $guardName) {
            return $permission->name === $name && $permission->guardName === $guardName;
        })->first();

        if (!$permission) {
            $permission = static::create(['name' => $name, 'guardName' => $guardName]);
        }

        return $permission;
    }

    /**
     * A permission can be applied to roles.
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(config('permission.models.role'), 'roles', 'permissionIds', 'roleIds');
    }

    /**
     * A permission belongs to some users of the model associated with its guard.
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany($this->helpers->getModelForGuard($this->attributes['guardName']), 'users', 'permissionIds', 'userIds');
    }

    /**
     * Find a permission by its name (and optionally guardName).
     *
     * @param string $name
     * @param string $guardName
     *
     * @return PermissionInterface
     * @throws PermissionDoesNotExist
     * @throws \ReflectionException
     */
    public static function findByName(string $name, string $guardName = null): PermissionInterface
    {
        $guardName = $guardName ?? (new Guard())->getDefaultName(static::class);

        $permission = static::getPermissions()->filter(function ($permission) use ($name, $guardName) {
            return $permission->name === $name && $permission->guardName === $guardName;
        })->first();

        if (!$permission) {
            $helpers = new Helpers();
            throw new PermissionDoesNotExist($helpers->getPermissionDoesNotExistMessage($name, $guardName));
        }

        return $permission;
    }

    /**
     * Get the current cached permissions.
     * @return Collection
     */
    protected static function getPermissions(): Collection
    {
        return \app(PermissionRegistrar::class)->getPermissions();
    }
}
