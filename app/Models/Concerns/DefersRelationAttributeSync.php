<?php

namespace App\Models\Concerns;

use Closure;

/**
 * Allows attribute mutators (setXAttribute) that synchronize related records
 * (BelongsToMany syncs, foreign key reassignments, etc.) to work correctly
 * even when the model instance has not been persisted yet.
 *
 * When a new model is filled via Model::create($data), Eloquent invokes the
 * attribute mutators before the model has an id. Any relation sync that
 * depends on $this->id, or on a relation method that requires the parent key
 * to already exist, would therefore fail or silently do nothing.
 *
 * Models using this trait can wrap such logic in syncRelationAttribute():
 * if the model does not exist yet the callback is stored on the model
 * instance and replayed automatically right after the model has been
 * created (once its id is available). If the model already exists, the
 * callback still runs immediately, preserving the current behaviour for
 * updates.
 */
trait DefersRelationAttributeSync
{
    /**
     * Callbacks queued while the model has not been persisted yet, keyed by
     * attribute name so a later mutator call replaces an earlier one.
     *
     * @var array<string, Closure>
     */
    protected array $pendingRelationAttributeSyncs = [];

    protected static function bootDefersRelationAttributeSync(): void
    {
        static::created(function ($model) {
            $model->flushPendingRelationAttributeSyncs();
        });
    }

    /**
     * Run the given relation sync callback immediately if the model already
     * exists, otherwise queue it to run once the model has been created.
     */
    protected function syncRelationAttribute(string $key, Closure $callback): void
    {
        if (! $this->exists) {
            $this->pendingRelationAttributeSyncs[$key] = $callback;

            return;
        }

        $callback($this);
    }

    /**
     * Replay any relation sync callbacks that were queued before the model
     * was persisted. Called automatically after the model is created.
     */
    public function flushPendingRelationAttributeSyncs(): void
    {
        if (empty($this->pendingRelationAttributeSyncs)) {
            return;
        }

        $callbacks = $this->pendingRelationAttributeSyncs;
        $this->pendingRelationAttributeSyncs = [];

        foreach ($callbacks as $callback) {
            $callback($this);
        }
    }
}
