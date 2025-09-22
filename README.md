# 🎫 Aplicador Automático de Cupones Multi-Categoría - PrestaShop

Un módulo para PrestaShop que aplica automáticamente un cupón adicional cuando se cumplen ciertas condiciones: tener un cupón base aplicado + cantidad mínima de productos en categorías específicas.

## 📋 Características

- ✅ **Aplicación automática inmediata**: Cuando el cliente aplica el cupón base y ya tiene la cantidad mínima de productos, se aplica el cupón extra instantáneamente
    
- ✅ **Detección dinámica**: Al añadir/quitar productos del carrito, verifica automáticamente si debe aplicar o remover el cupón extra
    
- ✅ **Multi-categoría**: Configurable para múltiples categorías de productos
    
- ✅ **Interfaz intuitiva**: Panel de administración fácil de usar con árbol de categorías
    
- ✅ **Logs detallados**: Registro completo de todas las acciones para debugging
    
- ✅ **Mensajes informativos**: Alertas en el carrito que informan al cliente del estado de los cupones
    

## 🚀 Instalación

1. Descarga o clona este repositorio
    
2. Sube la carpeta `ps_cupondinamico` a `/modules/` en tu instalación de PrestaShop
    
3. Ve a **Módulos y Servicios** en el back-office
    
4. Busca "Aplicador Automático de Cupones Multi-Categoría"
    
5. Haz clic en **Instalar**
    

## ⚙️ Configuración

## 1. Crear los Cupones en PrestaShop

Primero, ve a **Catálogo > Descuentos > Reglas del carrito** y crea dos cupones:

**Cupón Base** (ej: `VUELTA25`)

- Código: El que el cliente aplicará manualmente
    
- Descuento: Configura según tus necesidades
    
- Estado: Activo
    

**Cupón Extra** (ej: `EXTRA25`)

- Código: Se aplicará automáticamente
    
- Descuento: Adicional al cupón base
    
- Estado: Activo
    

## 2. Configurar el Módulo

1. Ve a **Módulos y Servicios > Aplicador Automático de Cupones Multi-Categoría**
    
2. Haz clic en **Configurar**
    
3. Completa los campos:
    
    - **Cupón BASE**: Código que el cliente aplicará manualmente
        
    - **Cupón EXTRA**: Código que se aplicará automáticamente
        
    - **Categorías**: Selecciona las categorías de productos elegibles
        
    - **Cantidad mínima**: Número mínimo de productos necesarios
        

## 🎯 ¿Cómo Funciona?

```text
A[Cliente aplica cupón base] --> B{¿Ya tiene 2+ productos?}     
B -->|Sí| C[Se aplica cupón extra inmediatamente]     
B -->|No| D[Cliente añade productos]     
D --> E{¿Llega a 2+ productos?}     
E -->|Sí| F[Se aplica cupón extra automáticamente]     
E -->|No| G[Sigue esperando]      
    
H[Cliente quita productos] --> I{¿Baja de 2 productos?}     
I -->|Sí| J[Se quita cupón extra]     
I -->|No| K[Mantiene ambos cupones]`
```

## Escenarios de Uso

1. **Cliente con 2+ productos aplica cupón base** → ✅ Ambos cupones se aplican inmediatamente
    
2. **Cliente aplica cupón base y luego añade productos** → ✅ Cupón extra se aplica al alcanzar la cantidad
    
3. **Cliente quita productos del carrito** → ✅ Cupón extra se remueve automáticamente si baja la cantidad
    

## 🛠️ Hooks Utilizados

El módulo se basa en tres hooks principales de PrestaShop:

- **`actionCartRuleApply`**: Detecta cuando se aplica un cupón[](https://www.prestashop.com/forums/topic/343065-hook-for-cart-update/)
    
- **`actionCartSave`**: Se ejecuta cuando se modifica el contenido del carrito[](https://devdocs.prestashop-project.org/9/modules/concepts/hooks/list-of-hooks/actioncartsave/)
    
- **`displayShoppingCart`**: Muestra mensajes informativos en el carrito
    

## 🔧 Compatibilidad

- **PrestaShop**: 1.7.0 - 1.8.x
    
- **PHP**: 5.6+
    
- **Bootstrap**: Incluido (interfaz de administración)
    

## 📊 Logging y Debugging

El módulo registra todas las acciones importantes:

```php

`PrestaShopLogger::addLog("Cupón base detectado: {$appliedCouponCode}", 1); PrestaShopLogger::addLog("Cupón extra aplicado inmediatamente: {$extraCode}", 1);`
```
Para ver los logs, ve a **Parámetros Avanzados > Logs** en el back-office.

## 🎨 Personalización

## Modificar Mensajes

Los mensajes mostrados al cliente se pueden personalizar en el método `hookDisplayShoppingCart`:

```php

`// Mensaje de éxito '¡Ambos cupones activos! <strong>' . $baseCode . '</strong> + <strong>' . $extraCode . '</strong>' // Mensaje informativo 'Añade ' . ($minQty - $quantity) . ' producto(s) más para activar <strong>' . $extraCode . '</strong>'`
```

## Añadir Más Condiciones

Puedes extender la funcionalidad modificando el método `processAutoCoupon`:

```php

`private function processAutoCoupon($cart) {     // Lógica existente...          // Añadir nueva condición (ejemplo: monto mínimo)     $cartTotal = $cart->getOrderTotal();     $minAmount = 50.00;          $shouldHaveExtra = $hasBaseCoupon && $quantity >= $minQty && $cartTotal >= $minAmount;          // Resto del código... }`
```

## 🐛 Resolución de Problemas

## El cupón extra no se aplica inmediatamente

- Verifica que ambos cupones existan y estén activos
    
- Revisa los logs en **Parámetros Avanzados > Logs**
    
- Asegúrate de que los productos estén en las categorías configuradas
    

## Los cupones se duplican

- Esto no debería ocurrir gracias a las validaciones `isCouponApplied()`
    
- Si persiste, revisa que no tengas otros módulos de cupones activos
    

## Categorías no funcionan

- Verifica que los productos estén asignados a las categorías correctas
    
- El módulo busca productos en cualquier categoría padre-hijo
    

## 📝 Licencia

Este proyecto está bajo la Licencia MIT - ver el archivo [LICENSE](https://www.perplexity.ai/search/LICENSE) para más detalles.

## 🤝 Contribuir

1. Fork el proyecto
    
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
    
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
    
4. Push a la rama (`git push origin feature/AmazingFeature`)
    
5. Abre un Pull Request
    

## 👨‍💻 Autor

**Alejandro González**  
[alexgo414](https://github.com/alexgo414)

## 🙏 Agradecimientos

- Documentación oficial de PrestaShop
    
- Comunidad de desarrolladores de PrestaShop
    
- Hooks y sistema de módulos de PrestaShop