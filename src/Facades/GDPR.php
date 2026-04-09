<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Facades;

use GraystackIt\Gdpr\Models\GdprRequest;
use GraystackIt\Gdpr\Support\GdprManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static GdprRequest requestDeletion(Model $subject)
 * @method static void cancelDeletion(GdprRequest $request)
 * @method static GdprRequest deleteImmediately(Model $subject)
 * @method static array processDueDeletions()
 * @method static GdprRequest requestExport(Model $subject)
 * @method static bool isDeletionPending(Model $subject)
 * @method static array|null packageInventory()
 *
 * @see GdprManager
 */
class GDPR extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GdprManager::class;
    }
}
