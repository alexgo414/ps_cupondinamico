<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_Cupondinamico extends Module
{
    public function __construct()
    {
        $this->name = 'ps_cupondinamico';
        $this->tab = 'pricing_promotion';
        $this->version = '4.2.0';
        $this->author = 'Alejandro González';
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('Aplicador Automático de Cupones Multi-Categoría');
        $this->description = $this->l('Aplica cupón adicional automáticamente cuando hay un cupón base + cantidad mínima en múltiples categorías.');
    }

    public function install()
    {
        return parent::install() &&
            Configuration::updateValue('AUTOCOUPON_ACTIVE', 1) &&
            Configuration::updateValue('AUTOCOUPON_BASE_CODE', 'BASICO2024') &&
            Configuration::updateValue('AUTOCOUPON_EXTRA_CODE', 'EXTRA2024') &&
            Configuration::updateValue('AUTOCOUPON_CATEGORIES', json_encode([12])) &&
            Configuration::updateValue('AUTOCOUPON_MIN_QTY', 2) &&
            $this->registerHook('actionCartSave') &&
            $this->registerHook('displayShoppingCart') &&
            $this->registerHook('actionCartRuleApply'); // ← CLAVE: Para aplicación inmediata
    }

    public function uninstall()
    {
        return parent::uninstall() &&
            Configuration::deleteByName('AUTOCOUPON_ACTIVE') &&
            Configuration::deleteByName('AUTOCOUPON_BASE_CODE') &&
            Configuration::deleteByName('AUTOCOUPON_EXTRA_CODE') &&
            Configuration::deleteByName('AUTOCOUPON_CATEGORIES') &&
            Configuration::deleteByName('AUTOCOUPON_MIN_QTY');
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitConfig')) {
            $output .= $this->processConfig();
        }

        $output .= $this->displayForm();
        $output .= $this->displayCurrentConfig();

        return $output;
    }

    private function processConfig()
    {
        $active = (int)Tools::getValue('AUTOCOUPON_ACTIVE');
        $baseCode = trim(Tools::getValue('AUTOCOUPON_BASE_CODE'));
        $extraCode = trim(Tools::getValue('AUTOCOUPON_EXTRA_CODE'));
        $categories = Tools::getValue('categoryBox');
        $minQty = (int)Tools::getValue('AUTOCOUPON_MIN_QTY');

        if (empty($baseCode) || empty($extraCode) || empty($categories) || $minQty <= 0) {
            return $this->displayError($this->l('Todos los campos son obligatorios.'));
        }

        if ($baseCode === $extraCode) {
            return $this->displayError($this->l('El cupón base y el extra deben ser diferentes.'));
        }

        // Verificar que ambos cupones existen
        $baseRuleId = CartRule::getIdByCode($baseCode);
        $extraRuleId = CartRule::getIdByCode($extraCode);

        if (!$baseRuleId) {
            return $this->displayError($this->l('El cupón base "' . $baseCode . '" no existe.'));
        }

        if (!$extraRuleId) {
            return $this->displayError($this->l('El cupón extra "' . $extraCode . '" no existe.'));
        }

        Configuration::updateValue('AUTOCOUPON_ACTIVE', $active);
        Configuration::updateValue('AUTOCOUPON_BASE_CODE', $baseCode);
        Configuration::updateValue('AUTOCOUPON_EXTRA_CODE', $extraCode);
        Configuration::updateValue('AUTOCOUPON_CATEGORIES', json_encode(array_map('intval', $categories)));
        Configuration::updateValue('AUTOCOUPON_MIN_QTY', $minQty);

        return $this->displayConfirmation($this->l('Configuración guardada correctamente.'));
    }

    private function displayForm()
    {
        $categoryTree = $this->generateCategoryTree();

        return '
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-cogs"></i> ' . $this->l('Configuración del Aplicador Automático') . '
            </div>
            <div class="panel-body">
                <div class="alert alert-info">
                    <strong>¿Cómo funciona?</strong><br>
                    1. El cliente aplica el <strong>cupón base</strong><br>
                    2. Si ya tiene <strong>' . Configuration::get('AUTOCOUPON_MIN_QTY') . '+ productos</strong> → Se aplica el <strong>cupón extra INMEDIATAMENTE</strong><br>
                    3. Si añade productos después → También se aplica automáticamente
                </div>
                <form method="post" class="form-horizontal">
                    <div class="form-group">
                        <label class="control-label col-lg-3">' . $this->l('Activar módulo') . '</label>
                        <div class="col-lg-2">
                            <select name="AUTOCOUPON_ACTIVE" class="form-control">
                                <option value="1" ' . (Configuration::get('AUTOCOUPON_ACTIVE') ? 'selected' : '') . '>Sí</option>
                                <option value="0" ' . (!Configuration::get('AUTOCOUPON_ACTIVE') ? 'selected' : '') . '>No</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-lg-3">' . $this->l('Cupón BASE') . '</label>
                        <div class="col-lg-4">
                            <input type="text" name="AUTOCOUPON_BASE_CODE" class="form-control" 
                                   value="' . htmlspecialchars(Configuration::get('AUTOCOUPON_BASE_CODE')) . '" 
                                   placeholder="Ej: VUELTA25" required>
                            <p class="help-block">El cliente aplica este cupón manualmente</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-lg-3">' . $this->l('Cupón EXTRA') . '</label>
                        <div class="col-lg-4">
                            <input type="text" name="AUTOCOUPON_EXTRA_CODE" class="form-control" 
                                   value="' . htmlspecialchars(Configuration::get('AUTOCOUPON_EXTRA_CODE')) . '" 
                                   placeholder="Ej: EXTRA25" required>
                            <p class="help-block">Se aplica automáticamente</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-lg-3">' . $this->l('Categorías') . '</label>
                        <div class="col-lg-6">
                            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 15px;">
                                ' . $categoryTree . '
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-lg-3">' . $this->l('Cantidad mínima') . '</label>
                        <div class="col-lg-2">
                            <input type="number" name="AUTOCOUPON_MIN_QTY" class="form-control" 
                                   value="' . (int)Configuration::get('AUTOCOUPON_MIN_QTY') . '" 
                                   min="1" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-lg-offset-3 col-lg-4">
                            <button type="submit" name="submitConfig" class="btn btn-primary">
                                <i class="icon-save"></i> ' . $this->l('Guardar') . '
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <script>
        $(document).ready(function() {
            $(".category-toggle").click(function(e) {
                e.preventDefault();
                var target = $(this).data("target");
                $("#" + target).toggle();
                var icon = $(this).find("i");
                if (icon.hasClass("icon-plus")) {
                    icon.removeClass("icon-plus").addClass("icon-minus");
                } else {
                    icon.removeClass("icon-minus").addClass("icon-plus");
                }
            });
        });
        </script>';
    }

    private function generateCategoryTree($id_category = null, $level = 0)
    {
        if ($id_category === null) {
            $id_category = (int)Configuration::get('PS_HOME_CATEGORY');
        }

        $selectedCategories = json_decode(Configuration::get('AUTOCOUPON_CATEGORIES'), true) ?: [];
        $categories = Category::getChildren($id_category, $this->context->language->id, true);
        $html = '';

        foreach ($categories as $category) {
            $hasChildren = Category::hasChildren($category['id_category'], $this->context->language->id);
            $isSelected = in_array($category['id_category'], $selectedCategories);
            
            $html .= '<div class="category-item">';
            
            if ($hasChildren) {
                $html .= '<a href="#" class="category-toggle" data-target="subcats_' . $category['id_category'] . '">';
                $html .= '<i class="icon-plus"></i>';
                $html .= '</a>';
            } else {
                $html .= '<i class="icon-circle-blank" style="color: #ccc; margin-right: 5px; width: 14px; display: inline-block;"></i>';
            }
            
            $html .= '<label>';
            $html .= '<input type="checkbox" name="categoryBox[]" value="' . $category['id_category'] . '"';
            if ($isSelected) {
                $html .= ' checked="checked"';
            }
            $html .= '> ';
            $html .= htmlspecialchars($category['name']) . ' <small>(ID: ' . $category['id_category'] . ')</small>';
            $html .= '</label>';
            
            if ($hasChildren) {
                $html .= '<div class="subcategories" id="subcats_' . $category['id_category'] . '" style="display: none; margin-left: 20px; border-left: 2px solid #e0e0e0; padding-left: 10px;">';
                $html .= $this->generateCategoryTree($category['id_category'], $level + 1);
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }

        return $html;
    }

    private function displayCurrentConfig()
    {
        $selectedCategories = json_decode(Configuration::get('AUTOCOUPON_CATEGORIES'), true) ?: [];
        $categoryNames = [];

        foreach ($selectedCategories as $categoryId) {
            $category = new Category($categoryId, $this->context->language->id);
            if (Validate::isLoadedObject($category)) {
                $categoryNames[] = $category->name . ' (ID: ' . $categoryId . ')';
            }
        }

        return '
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-info"></i> Configuración Actual
            </div>
            <div class="panel-body">
                <ul>
                    <li><strong>Estado:</strong> ' . (Configuration::get('AUTOCOUPON_ACTIVE') ? 'Activo' : 'Inactivo') . '</li>
                    <li><strong>Cupón base:</strong> <code>' . htmlspecialchars(Configuration::get('AUTOCOUPON_BASE_CODE')) . '</code></li>
                    <li><strong>Cupón extra:</strong> <code>' . htmlspecialchars(Configuration::get('AUTOCOUPON_EXTRA_CODE')) . '</code></li>
                    <li><strong>Cantidad mínima:</strong> ' . (int)Configuration::get('AUTOCOUPON_MIN_QTY') . ' productos</li>
                    <li><strong>Categorías:</strong> ' . (empty($categoryNames) ? 'Ninguna' : implode(', ', $categoryNames)) . '</li>
                </ul>
            </div>
        </div>';
    }

    // ===== HOOKS =====

    public function hookActionCartRuleApply($params)
    {
        // ← CLAVE: Este hook detecta cuando se aplica un cupón
        if (!Configuration::get('AUTOCOUPON_ACTIVE') || !isset($params['cart']) || !isset($params['cart_rule'])) {
            return;
        }

        $cart = $params['cart'];
        $appliedCouponCode = $params['cart_rule']->code;
        $configuredBaseCoupon = Configuration::get('AUTOCOUPON_BASE_CODE');

        // Solo actuar si el cupón aplicado es nuestro cupón base configurado
        if ($appliedCouponCode === $configuredBaseCoupon) {
            PrestaShopLogger::addLog("Cupón base detectado: {$appliedCouponCode}", 1);
            $this->processImmediateApplication($cart);
        }
    }

    public function hookActionCartSave($params) 
    {
        if (!Configuration::get('AUTOCOUPON_ACTIVE') || !isset($params['cart'])) {
            return;
        }

        $this->processAutoCoupon($params['cart']);
    }

    public function hookDisplayShoppingCart($params) 
    {
        if (!Configuration::get('AUTOCOUPON_ACTIVE')) {
            return '';
        }

        $cart = $this->context->cart;
        $baseCode = Configuration::get('AUTOCOUPON_BASE_CODE');
        $extraCode = Configuration::get('AUTOCOUPON_EXTRA_CODE');
        $quantity = $this->getTotalCategoriesQuantity($cart);
        $minQty = (int)Configuration::get('AUTOCOUPON_MIN_QTY');
        
        $hasBaseCoupon = $this->isCouponApplied($cart, $baseCode);
        $hasExtraCoupon = $this->isCouponApplied($cart, $extraCode);

        if ($hasBaseCoupon && $quantity >= $minQty && $hasExtraCoupon) {
            return '<div class="alert alert-success">
                        <i class="icon-check"></i>
                        ¡Ambos cupones activos! <strong>' . htmlspecialchars($baseCode) . '</strong> + <strong>' . htmlspecialchars($extraCode) . '</strong>
                    </div>';
        } elseif ($hasBaseCoupon && $quantity < $minQty) {
            return '<div class="alert alert-info">
                        <i class="icon-info"></i>
                        Tienes <strong>' . htmlspecialchars($baseCode) . '</strong>. 
                        Añade ' . ($minQty - $quantity) . ' producto(s) más para activar <strong>' . htmlspecialchars($extraCode) . '</strong>
                    </div>';
        }

        return '';
    }

    // ===== MÉTODOS PRINCIPALES =====

    private function processImmediateApplication($cart)
    {
        // Se ejecuta inmediatamente cuando se aplica el cupón base
        $extraCode = Configuration::get('AUTOCOUPON_EXTRA_CODE');
        $quantity = $this->getTotalCategoriesQuantity($cart);
        $minQty = (int)Configuration::get('AUTOCOUPON_MIN_QTY');
        $hasExtraCoupon = $this->isCouponApplied($cart, $extraCode);

        PrestaShopLogger::addLog("Verificando aplicación inmediata: qty={$quantity}, min={$minQty}, hasExtra=" . ($hasExtraCoupon ? 'true' : 'false'), 1);

        if ($quantity >= $minQty && !$hasExtraCoupon) {
            $this->applyCoupon($cart, $extraCode);
            PrestaShopLogger::addLog("Cupón extra aplicado inmediatamente: {$extraCode}", 1);
        }
    }

    private function processAutoCoupon($cart)
    {
        $baseCode = Configuration::get('AUTOCOUPON_BASE_CODE');
        $extraCode = Configuration::get('AUTOCOUPON_EXTRA_CODE');
        $minQty = (int)Configuration::get('AUTOCOUPON_MIN_QTY');
        
        if (empty($baseCode) || empty($extraCode)) {
            return;
        }

        $hasBaseCoupon = $this->isCouponApplied($cart, $baseCode);
        $quantity = $this->getTotalCategoriesQuantity($cart);
        $hasExtraCoupon = $this->isCouponApplied($cart, $extraCode);

        $shouldHaveExtra = $hasBaseCoupon && $quantity >= $minQty;

        if ($shouldHaveExtra && !$hasExtraCoupon) {
            $this->applyCoupon($cart, $extraCode);
        } elseif (!$shouldHaveExtra && $hasExtraCoupon) {
            $this->removeCoupon($cart, $extraCode);
        }
    }

    private function getTotalCategoriesQuantity($cart)
    {
        $selectedCategories = json_decode(Configuration::get('AUTOCOUPON_CATEGORIES'), true) ?: [];
        $products = $cart->getProducts();
        $totalQuantity = 0;

        foreach ($products as $product) {
            $productCategories = Product::getProductCategories($product['id_product']);
            
            foreach ($selectedCategories as $selectedCategory) {
                if (in_array($selectedCategory, $productCategories)) {
                    $totalQuantity += (int)$product['cart_quantity'];
                    break;
                }
            }
        }

        return $totalQuantity;
    }

    private function isCouponApplied($cart, $couponCode)
    {
        $cartRules = $cart->getCartRules();
        foreach ($cartRules as $cartRule) {
            if ($cartRule['code'] === $couponCode) {
                return true;
            }
        }
        return false;
    }

    private function applyCoupon($cart, $couponCode)
    {
        try {
            $cartRuleId = CartRule::getIdByCode($couponCode);
            if ($cartRuleId) {
                $cart->addCartRule($cartRuleId);
                PrestaShopLogger::addLog("Cupón aplicado exitosamente: {$couponCode}", 1);
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog("Error aplicando cupón {$couponCode}: " . $e->getMessage(), 3);
        }
    }

    private function removeCoupon($cart, $couponCode)
    {
        try {
            $cartRules = $cart->getCartRules();
            foreach ($cartRules as $cartRule) {
                if ($cartRule['code'] === $couponCode) {
                    $cart->removeCartRule($cartRule['id_cart_rule']);
                    break;
                }
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog("Error quitando cupón: " . $e->getMessage(), 3);
        }
    }
}
