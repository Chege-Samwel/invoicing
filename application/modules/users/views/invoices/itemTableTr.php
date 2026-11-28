<tr>
    <td>
        <input type="hidden" name="item_from_list[]" value="0">
        <input type="hidden" name="is_item_update[]" value="<?= isset($itemPost) ? $itemPost['id'] : '0' ?>">
        <div class="actions">
            <a href="javascript:void(0);" class="btn btn-default delete-item" aria-label="<?= html_escape(lang('delete')) ?>">
                <i class="fa fa-times" aria-hidden="true"></i>
            </a>
            <a href="javascript:void(0);" class="btn btn-default move-me" aria-label="Reorder item by dragging">
                <i class="fa fa-sort" aria-hidden="true"></i>
            </a>
            <button type="button" class="btn btn-default item-move-up" aria-label="Move item up">&uarr;</button>
            <button type="button" class="btn btn-default item-move-down" aria-label="Move item down">&darr;</button>
        </div>
    </td>
    <td data-mobile-label="<?= html_escape(lang('create_inv_item')) ?>">
        <input type="text" value="<?= isset($itemPost) ? $itemPost['name'] : '' ?>" name="items_names[]" class="form-control field field-item-name" aria-label="<?= html_escape(lang('create_inv_item')) ?>">
        <a href="javascript:void(0);" data-choose-title="<?= lang('choose_item') ?>" data-selector-type="item" class="choose">
            <i class="fa fa-bars" aria-hidden="true"></i>
            <span><?= lang('create_inv_choose') ?></span>
        </a>
    </td>
    <td class="min-w-190" data-mobile-label="<?= html_escape(lang('create_inv_quantity')) ?>">
        <input type="text" value="<?= isset($itemPost) ? $itemPost['quantity'] : '1.00' ?>" name="items_quantities[]" class="form-control field quantity-field" inputmode="decimal" aria-label="<?= html_escape(lang('create_inv_quantity')) ?>">
        <div class="quantity-type">
            <select class="form-control" name="items_quantity_types[]" aria-label="<?= html_escape(lang('create_inv_quantity')) ?> unit" data-my-id="1">
                <?php foreach ($quantityTypes as $quantityType) { ?>
                    <option value="<?= $quantityType['name'] ?>"><?= $quantityType['name'] ?></option>
                <?php } if (isset($itemPost)) { ?>
                    <option value="<?= $itemPost['quantity_type'] ?>" selected=""><?= $itemPost['quantity_type'] ?></option>
                <?php } ?>
                <option value="--">--</option>
                <option value="createNewQuantity"><?= lang('create_new_quantity') ?></option>
            </select> 
        </div>
        <span class="item-operator">x</span>
    </td>
    <td class="min-w-180" data-mobile-label="<?= html_escape(lang('create_inv_price')) ?>">
        <input type="text" value="<?= isset($itemPost) ? $itemPost['single_price'] : '0.00' ?>" name="items_prices[]" class="form-control field price-field" inputmode="decimal" aria-label="<?= html_escape(lang('create_inv_price')) ?>">
        <span class="item-operator">=</span>
    </td>
    <td class="text-right" data-mobile-label="<?= html_escape(lang('create_inv_total')) ?>">
        <div class="item-total-price">
            <span class="item-total"><?= isset($itemPost) ? $itemPost['total_price'] : '0.00' ?></span>
            <input type="hidden" class="item-total field" value="<?= isset($itemPost) ? $itemPost['total_price'] : '0.00' ?>" name="items_totals[]">
            <span class="currency-text">
                <?= $theCurrency ?>
            </span>
        </div>
    </td>
</tr> 