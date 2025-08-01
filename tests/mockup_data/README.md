# 📄 Archivos Mockup de Testing SalesLayer WooCommerce

## 🎯 Propósito

Esta carpeta contiene archivos JSON de ejemplo listos para usar en el testing de sincronización de SalesLayer WooCommerce. Los archivos están organizados por tipo y pueden ser editados directamente según las necesidades específicas de testing.

## 📁 Estructura de Archivos

```
mockup_data/
├── categories/          # Categorías de productos
│   ├── category_1001.json    # Categoría Principal Test
│   ├── category_1002.json    # Ropa de Hombre Test
│   └── category_1003.json    # Ropa de Mujer Test
├── products/            # Productos principales
│   ├── product_2001.json     # Camiseta Básica Hombre Test
│   ├── product_2002.json     # Vestido Casual Mujer Test
│   └── product_2003.json     # Pantalón Vaquero Unisex Test
├── variants/            # Variantes de productos
│   ├── variant_3001.json     # Camiseta Blanco M
│   ├── variant_3002.json     # Camiseta Negro L
│   ├── variant_3003.json     # Vestido Azul S
│   ├── variant_3004.json     # Vestido Rosa M
│   ├── variant_3005.json     # Vaquero Azul 32
│   └── variant_3006.json     # Vaquero Negro 34
└── README.md           # Esta documentación
```

## 🛍️ Productos de Ejemplo Incluidos

### 📁 **Categorías (3 elementos)**
1. **Categoría Principal Test** (ID: 1001) - Categoría padre
2. **Ropa de Hombre Test** (ID: 1002) - Subcategoría masculina
3. **Ropa de Mujer Test** (ID: 1003) - Subcategoría femenina

### 🛍️ **Productos (3 elementos)**
1. **Camiseta Básica Hombre Test** (ID: 2001)
   - SKU: TEST-CAMISETA-HOMBRE-001
   - Precio: €24.99 (Oferta: €19.99)
   - Categorías: Principal, Hombre

2. **Vestido Casual Mujer Test** (ID: 2002)
   - SKU: TEST-VESTIDO-MUJER-001
   - Precio: €49.99 (Oferta: €39.99)
   - Categorías: Principal, Mujer

3. **Pantalón Vaquero Unisex Test** (ID: 2003)
   - SKU: TEST-VAQUERO-UNISEX-001
   - Precio: €69.99 (Oferta: €54.99)
   - Categorías: Principal, Hombre, Mujer

### 🎨 **Variantes (6 elementos)**
1. **Camiseta Blanco M** (ID: 3001) - Producto 2001
2. **Camiseta Negro L** (ID: 3002) - Producto 2001
3. **Vestido Azul S** (ID: 3003) - Producto 2002
4. **Vestido Rosa M** (ID: 3004) - Producto 2002
5. **Vaquero Azul 32** (ID: 3005) - Producto 2003
6. **Vaquero Negro 34** (ID: 3006) - Producto 2003

## ✏️ Personalización de Archivos

### 🔧 **Edición Individual**
Cada archivo JSON puede ser editado independientemente:

```bash
# Editar una categoría específica
nano categories/category_1001.json

# Editar un producto específico
nano products/product_2001.json

# Editar una variante específica
nano variants/variant_3001.json
```

### ➕ **Agregar Nuevos Elementos**

**Nueva Categoría:**
```bash
cp categories/category_1001.json categories/category_1004.json
# Editar category_1004.json con nuevos datos
```

**Nuevo Producto:**
```bash
cp products/product_2001.json products/product_2004.json
# Editar product_2004.json con nuevos datos
```

**Nueva Variante:**
```bash
cp variants/variant_3001.json variants/variant_3007.json
# Editar variant_3007.json con nuevos datos
```

### 🗑️ **Eliminar Elementos**
```bash
# Eliminar una variante específica
rm variants/variant_3006.json

# Eliminar un producto completo
rm products/product_2003.json
```

## 📋 **Estructura de Archivos JSON**

Cada archivo JSON tiene la estructura exacta de la tabla `slyr_wc_api_syncdata`:

### **Campos de la Tabla:**
- `id` - ID único del registro en la tabla
- `sync_type` - Tipo de sincronización ("update", "insert", "delete")
- `item_type` - Tipo de elemento ("category", "product", "product_format")
- `sync_tries` - Número de intentos de sincronización
- `item_data` - Datos del elemento como objeto JSON (se codifica automáticamente al insertar)
- `sync_params` - Parámetros de configuración como objeto JSON (se codifica automáticamente al insertar)

### **Campos Importantes a Personalizar:**

**En `item_data` para Categorías:**
- `data.category_name_es` - Nombre de la categoría
- `data.category_description_es` - Descripción de la categoría
- `data.category_image_es` - Imágenes de la categoría
- `ID` - Identificador único de la categoría
- `ID_parent` - Array con IDs de categorías padre

**En `item_data` para Productos:**
- `data.product_name_es` - Nombre del producto
- `data.product_sku` - SKU único del producto
- `data.product_price` - Precio regular
- `data.product_sale_price` - Precio de oferta
- `ID` - Identificador único del producto
- `ID_catalogue` - Array con IDs de categorías

**En `item_data` para Variantes:**
- `format_data.data.format_sku` - SKU único de la variante
- `format_data.data.format_regular_price` - Precio de la variante
- `format_data.data.color` - Color de la variante
- `format_data.data.size` - Talla de la variante
- `format_data.ID` - ID único de la variante
- `format_data.ID_products` - ID del producto padre

## ⚠️ **Consideraciones Importantes**

### **Formato JSON Decodificado:**
- Los campos `item_data` y `sync_params` son objetos JSON normales (fáciles de editar)
- El sistema automáticamente los codifica con `json_encode()` al insertar en la base de datos
- Puedes editar directamente los campos sin preocuparte por el escape de caracteres

### **IDs Únicos:**
- `id` de tabla: 43001-45999 (único por registro)
- IDs de elementos: Categorías 1001-1999, Productos 2001-2999, Variantes 3001-3999
- SKUs únicos con prefijo "TEST-"

### **Relaciones:**
- Variantes: `format_data.ID_products` debe referenciar un producto existente
- Productos: `ID_catalogue` debe referenciar categorías existentes
- Categorías: `ID_parent` debe referenciar categorías padre existentes

### **Tipos de Elementos:**
- `item_type`: "category" para categorías
- `item_type`: "product" para productos
- `item_type`: "product_format" para variantes

## 🚀 **Uso en Testing**

### **Ejecutar Test con Archivos Actuales:**
```bash
php run_test.php quick
```

### **Validar Archivos JSON:**
```bash
php run_test.php validate-only
```

### **Ver Estado de Archivos:**
Usar la interfaz web de administración para ver el estado detallado de todos los archivos mockup.

## 💡 **Consejos de Uso**

1. **Backup antes de editar:** Hacer copia de seguridad antes de modificaciones importantes
2. **Validar JSON:** Asegurar que el JSON sea válido después de editar
3. **Probar incrementalmente:** Hacer cambios pequeños y probar frecuentemente
4. **Documentar cambios:** Mantener notas sobre modificaciones específicas
5. **Usar datos realistas:** Incluir datos que reflejen casos de uso reales

---

**Desarrollado por:** Peter  
**Fecha:** 23 de julio de 2025  
**Versión:** 1.0.0  
**Compatible con:** SalesLayer WooCommerce v2.5.2+
