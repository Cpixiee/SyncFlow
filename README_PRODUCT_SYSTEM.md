# SyncFlow Product Management System

## 📋 Overview
Sistem manajemen produk dengan fitur pengukuran kompleks yang mendukung evaluasi OK/NG berdasarkan formula matematika dan rule-based evaluation. Sistem ini dirancang untuk mengelola produk dengan berbagai kategori testing (Tube Test, Wire Test Reguler, Shield Wire Test) dan pengukuran yang kompleks dengan 5 langkah detail untuk setiap measurement point.

## 🎯 Filosofi Sistem
Sistem ini dibangun dengan filosofi bahwa setiap produk memiliki multiple measurement points, dan setiap measurement point memiliki 5 step konfigurasi yang detail:
1. **Setup Measurement Item** - Konfigurasi dasar pengukuran
2. **Variables (Optional)** - Variabel untuk formula processing
3. **Pre-Processing Formula (Optional)** - Formula untuk processing per sample
4. **Evaluation Type** - Cara evaluasi OK/NG (per sample atau joint)
5. **Rule Evaluation** - Aturan OK/NG berdasarkan nilai threshold

## 🏗️ Arsitektur Sistem

### Quarter System
Sistem quarter digunakan sebagai acuan filter dan periode produk:
- **Q1**: Januari - Maret  
- **Q2**: April - Juni
- **Q3**: Juli - September
- **Q4**: Oktober - Desember

Setiap produk akan otomatis dikaitkan dengan quarter aktif saat pembuatan.

### Product Categories
Sistem mendukung 3 kategori utama dengan hierarki produk:

#### 1. Tube Test
- **VO**: Stand-alone product
- **COT**: COTO, COT, COTO-FR, COT-FR, CORUTUBE variants
- **RFCOT**: Stand-alone product  
- **HCOT**: Stand-alone product

#### 2. Wire Test Reguler
- **CAVS**: CAVS, ACCAVS
- **CIVUS**: CIVUS, ACCIVUS
- **AVSS, AVSSH, AVS, AV**: Stand-alone products

#### 3. Shield Wire Test
- **CAVSAS**: CIVUSAS, CIVUSAS-S, CAVSAS-S, AVSSHCS, AVSSCS, AVSSCS-S

## 🔧 Alur Logic Product - Detail Lengkap

### Step 1: Basic Info Product
User memilih kategori produk dan product name. Sistem akan memvalidasi bahwa product name sesuai dengan kategori yang dipilih.

**Frontend Logic:**
1. User pilih **Product Category** dari dropdown (didapat dari endpoint `/products/categories`)
2. Berdasarkan kategori, frontend filter opsi **Product Name** yang available
3. Fields optional (ref_spec_number, nom_size_vo, dll) bisa ditambah manual via button "Add Field"

**Backend Implementation:**
- Validasi `product_category_id` exists di database
- Validasi `product_name` sesuai dengan `ProductCategory::isValidProductName()`
- Check duplicate dengan `Product::checkProductExists()` untuk basic_info yang sama

```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "VO",
    "ref_spec_number": "SPEC-001",  // optional
    "nom_size_vo": "2.5mm",         // optional
    "article_code": "ART-001",      // optional
    "no_document": "DOC-001",       // optional
    "no_doc_reference": "REF-001"   // optional
  }
}
```

### Step 2: Detail Pengukuran (5 Sub-Steps per Measurement Point)

Setiap produk bisa memiliki **multiple measurement points**. Setiap measurement point wajib dikonfigurasi dengan 5 langkah berikut:

#### Step 2.1: Setup Measurement Item (MANDATORY)
Konfigurasi dasar untuk setiap measurement point.

**Frontend Logic:**
1. User input **nama point pengukuran** (string)
2. **name_id** auto-generate dari nama (convert ke snake_case: "Inside Diameter" → "inside_diameter")
3. **sample_amount** (integer) - berapa banyak sample/example product yang akan diukur
4. **source** pilih dari 3 opsi:
   - **INSTRUMENT**: Data diambil dari IoT device
   - **MANUAL**: User input manual saat measurement
   - **DERIVED**: Ambil dari measurement item lain di product yang sama
5. **type**: SINGLE (1 nilai) atau BEFORE_AFTER (2 nilai: sebelum & sesudah)
6. **nature**: QUANTITATIVE (angka) atau QUALITATIVE (visual check)

**Backend Implementation:**
- Validasi semua field required
- Auto-generate `name_id` dari `name` dengan snake_case conversion
- Jika `source = DERIVED`, `sample_amount` otomatis sama dengan measurement item yang di-derive
- Validasi `source_instrument_id` jika `source = INSTRUMENT`
- Validasi `source_derived_name_id` jika `source = DERIVED`

```json
{
  "setup": {
    "name": "Inside Diameter",
    "name_id": "inside_diameter",        // auto-generated
    "sample_amount": 5,
    "source": "INSTRUMENT",              // INSTRUMENT | MANUAL | DERIVED
    "source_instrument_id": 123,         // required when source = INSTRUMENT
    "source_derived_name_id": null,      // required when source = DERIVED
    "type": "SINGLE",                    // SINGLE | BEFORE_AFTER
    "nature": "QUANTITATIVE"             // QUALITATIVE | QUANTITATIVE
  }
}
```

**Source Types Detail:**
- **INSTRUMENT**: Data otomatis dari IoT → Frontend → Backend (jumlah data otomatis sesuai sample_amount)
- **MANUAL**: User input manual saat measurement
- **DERIVED**: Ambil dari measurement item lain dalam produk yang sama (muncul di dropdown "other measurement data")

#### Step 2.2: Setup Variables (OPTIONAL)
Untuk processing formula dengan mathematical operations. Digunakan jika measurement item membutuhkan processing formula.

**Frontend Logic:**
1. Initially empty, user bisa add variables via button "Add Variable"
2. **Type** pilih dari 3 opsi:
   - **FIXED**: Nilai konstan (tinggi = 20.0)
   - **FORMULA**: Hasil dari formula matematika
   - **MANUAL**: Input manual saat measurement (independent variable)
3. **Name** harus unique dan hanya alphabet + underscore
4. **Value** wajib diisi untuk type FIXED
5. **Formula** wajib diisi untuk type FORMULA
6. **is_show**: untuk MANUAL selalu true, untuk lainnya bisa dipilih

**Backend Implementation:**
- Validasi nama variable unique dalam measurement point
- Validasi nama hanya alphabet + underscore
- Type FIXED: `value` required, `formula` null
- Type FORMULA: `formula` required, `value` null  
- Type MANUAL: `is_show` auto-set true
- Formula diproses dengan NXP MathExecutor

**Use Cases:**
- **FIXED**: Konstanta seperti tinggi, lebar, gravitasi
- **FORMULA**: Kalkulasi seperti `THICKNESS = (AVG(THICKNESS_A) + AVG(THICKNESS_B)) / 2`
- **MANUAL**: Variable yang diinput user saat measurement seperti room temperature

```json
{
  "variables": [
    {
      "type": "FIXED",
      "name": "tinggi",
      "value": 20.0,                    // required for FIXED
      "formula": null,
      "is_show": false
    },
    {
      "type": "FORMULA", 
      "name": "THICKNESS",
      "value": null,
      "formula": "(AVG(THICKNESS_A) + AVG(THICKNESS_B) + AVG(THICKNESS_C)) / 3",  // required for FORMULA
      "is_show": true
    },
    {
      "type": "MANUAL",
      "name": "room_temperature",
      "value": null,
      "formula": null,
      "is_show": true                   // auto-filled as true for MANUAL
    }
  ]
}
```

#### Step 2.3: Setup Pre-Processing Formula (OPTIONAL)
Formula yang dijalankan untuk **setiap sample/example** product. Berbeda dengan Step 2.2 yang untuk measurement item secara keseluruhan.

**Frontend Logic:**
1. Initially empty, user bisa add via button "Add Formula"
2. **Name** harus unique dan hanya alphabet + underscore
3. **Formula** menggunakan raw values + variables dari Step 2.2
4. **is_show**: apakah hasil formula ditampilkan dalam measurement

**Backend Implementation:**
- Formula dijalankan per sample menggunakan NXP MathExecutor
- Input: raw values (single_value, before/after) + variables dari Step 2.2
- Output: hasil formula disimpan untuk evaluasi di Step 2.4
- Validasi nama formula unique dalam measurement point

**Use Case Example:**
- Measurement item "Inside Diameter" punya 5 sample
- Step 2.2 ada variable CROSS_SECTION = THICKNESS * 5
- Step 2.3 ada formula ROOM_TEMP_NORMALIZED = ROOM_TEMP / CROSS_SECTION
- Formula ini dijalankan untuk **setiap sample** (5x), bukan sekali untuk keseluruhan

```json
{
  "pre_processing_formulas": [
    {
      "name": "ROOM_TEMP_NORMALIZED",
      "formula": "ROOM_TEMP / CROSS_SECTION",     // menggunakan variable dari Step 2.2
      "is_show": true
    },
    {
      "name": "PRESSURE_DIFF", 
      "formula": "after - before",               // untuk type BEFORE_AFTER
      "is_show": true
    }
  ]
}
```

#### Step 2.4: Evaluation Type (MANDATORY)
Menentukan cara evaluasi OK/NG untuk measurement item ini.

**Frontend Logic:**
1. User pilih salah satu dari 3 opsi
2. Jika pilih PER_SAMPLE → langsung ke Step 2.6 (Rule Evaluation)
3. Jika pilih JOINT → akan ada Step 2.5 (Aggregation) 
4. Jika pilih SKIP_CHECK → langsung selesai (tidak ada Step 2.5 & 2.6)

**Backend Implementation:**
- Validasi evaluation_type adalah salah satu dari enum yang valid
- PER_SAMPLE: `per_sample_setting` required
- JOINT: `joint_setting` required  
- SKIP_CHECK: tidak perlu setting tambahan

```json
{
  "evaluation_type": "PER_SAMPLE"    // PER_SAMPLE | JOINT | SKIP_CHECK
}
```

**Evaluation Types Detail:**
- **PER_SAMPLE**: 
  - Evaluasi dilakukan per sample/example
  - Semua sample harus OK agar measurement item OK
  - Example: 5 sample → sample 1-4 OK, sample 5 NG → measurement item NG
  
- **JOINT**: 
  - Semua sample diagregasi jadi 1 nilai final
  - Evaluasi hanya dilakukan pada nilai final tersebut  
  - Example: 5 sample → AVG semua sample → evaluasi AVG-nya
  
- **SKIP_CHECK**: 
  - Tidak ada evaluasi OK/NG
  - Measurement item selalu dianggap OK

#### Step 2.5: Evaluation Setting (CONDITIONAL)
Configuration untuk evaluasi berdasarkan evaluation type dari Step 2.4.

**A. Untuk PER_SAMPLE:**
User pilih variable mana yang digunakan untuk evaluasi per sample.

**Frontend Logic:**
1. Tampilkan dropdown dengan opsi:
   - "Raw Data" (langsung nilai mentah dari pengukuran)
   - Semua pre-processing formula dari Step 2.3 (jika ada)
2. User pilih salah satu (XOR - hanya boleh satu)
3. Jika pilih "Raw Data" → `is_raw_data: true`
4. Jika pilih formula → `pre_processing_formula_name: "nama_formula"`

**Backend Implementation:**
- Validasi XOR: `is_raw_data XOR pre_processing_formula_name`
- Jika `is_raw_data=true`, gunakan nilai mentah (single_value/before_after)
- Jika `pre_processing_formula_name`, gunakan hasil formula dari Step 2.3

```json
{
  "evaluation_setting": {
    "per_sample_setting": {
      "is_raw_data": true,                    // XOR dengan pre_processing_formula_name
      "pre_processing_formula_name": null
    }
  }
}
```

**B. Untuk JOINT (Aggregation):**
User buat formula untuk agregasi semua sample jadi 1 nilai final.

**Frontend Logic:**
1. Tampilkan "Step 5: Aggregation" setelah Step 2.4
2. User bisa add multiple formulas
3. **Hanya 1 formula** yang boleh diset sebagai `is_final_value: true`
4. Formula final ini yang digunakan untuk evaluasi OK/NG

**Backend Implementation:**
- Validasi hanya 1 formula dengan `is_final_value: true`
- Formula dijalankan dengan input: hasil dari semua sample
- Support fungsi agregasi: AVG(), SUM(), MIN(), MAX()

```json
{
  "evaluation_setting": {
    "joint_setting": {
      "formulas": [
        {
          "name": "AVG_TEMP",
          "formula": "AVG(ROOM_TEMP_NORMALIZED)",    // agregasi dari semua sample
          "is_final_value": false
        },
        {
          "name": "FORCE", 
          "formula": "AVG_TEMP * 9.80665",
          "is_final_value": true                     // ini yang digunakan untuk evaluasi
        }
      ]
    }
  }
}
```

**C. Untuk QUALITATIVE Nature:**
Jika setup.nature = QUALITATIVE, tambahkan label setting.

```json
{
  "evaluation_setting": {
    "qualitative_setting": {
      "label": "Visual Check - No Defects Found"
    }
  }
}
```

#### Step 2.6: Rule Evaluation (CONDITIONAL)
Rule untuk menentukan OK/NG. **Hanya untuk setup.nature = QUANTITATIVE**.

**Frontend Logic:**
1. Jika nature = QUALITATIVE → skip step ini
2. Jika nature = QUANTITATIVE → wajib setting rule
3. **Rule** pilih dari 3 opsi:
   - **MIN**: Nilai minimum yang diterima
   - **MAX**: Nilai maksimum yang diterima  
   - **BETWEEN**: Range nilai dengan tolerance
4. **Unit** untuk display (mm, cm, kg, dll)
5. **Value** sebagai base/target value
6. **Tolerance** hanya untuk BETWEEN

**Backend Implementation:**
- Jika `setup.nature = QUALITATIVE` → `rule_evaluation_setting = null`
- Jika `setup.nature = QUANTITATIVE` → `rule_evaluation_setting` required
- Validasi `tolerance_minus` dan `tolerance_plus` required untuk rule BETWEEN
- Logic evaluasi:
  - MIN: `measured_value >= rule_value` 
  - MAX: `measured_value <= rule_value`
  - BETWEEN: `(rule_value - tolerance_minus) <= measured_value <= (rule_value + tolerance_plus)`

```json
{
  "rule_evaluation_setting": {
    "rule": "BETWEEN",              // MIN | MAX | BETWEEN
    "unit": "mm",
    "value": 14.4,                  // base/target value
    "tolerance_minus": 0.3,         // required for BETWEEN
    "tolerance_plus": 0.3           // required for BETWEEN
  }
}
```

**Rule Types Examples:**
- **MIN**: `value >= 14.1` → OK jika >= 14.1, NG jika < 14.1
- **MAX**: `value <= 14.7` → OK jika <= 14.7, NG jika > 14.7  
- **BETWEEN**: `14.1 <= value <= 14.7` → OK jika antara 14.1-14.7, NG jika di luar range

**Complete Measurement Point Example:**
```json
{
  "setup": {
    "name": "Inside Diameter",
    "name_id": "inside_diameter",
    "sample_amount": 5,
    "source": "MANUAL",
    "type": "SINGLE", 
    "nature": "QUANTITATIVE"
  },
  "variables": [
    {
      "type": "FIXED",
      "name": "target_diameter", 
      "value": 14.4,
      "is_show": false
    }
  ],
  "pre_processing_formulas": [
    {
      "name": "diameter_normalized",
      "formula": "single_value / target_diameter",
      "is_show": true
    }
  ],
  "evaluation_type": "PER_SAMPLE",
  "evaluation_setting": {
    "per_sample_setting": {
      "is_raw_data": true,
      "pre_processing_formula_name": null
    }
  },
  "rule_evaluation_setting": {
    "rule": "BETWEEN",
    "unit": "mm",
    "value": 14.4,
    "tolerance_minus": 0.3,
    "tolerance_plus": 0.3
  }
}
```

## 📊 Database Schema

### Tables Created:
1. **quarters**: Menyimpan data quarter dengan period dates
2. **product_categories**: Kategori produk dengan enum products
3. **products**: Data produk dengan measurement points (JSON)
4. **product_measurements**: Hasil pengukuran dan evaluasi

### Key Relationships:
- Product → Quarter (many-to-one)
- Product → ProductCategory (many-to-one) 
- ProductMeasurement → Product (many-to-one)
- ProductMeasurement → LoginUser (many-to-one, measured_by)

## 🔗 API Endpoints

### Base URL
```
Local: http://localhost:8000/api/v1
Production: http://103.236.140.19:2020/api/v1
```

### Product Endpoints

#### 1. **POST** `/products`
Create new product dengan measurement points.

**Headers:**
```
Authorization: Bearer {JWT_TOKEN}
Content-Type: application/json
```

**Request Body:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "VO",
    "ref_spec_number": "SPEC-001",
    "nom_size_vo": "2.5mm",
    "article_code": "ART-001"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Inside Diameter",
        "name_id": "inside_diameter", 
        "sample_amount": 5,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm", 
        "value": 14.4,
        "tolerance_minus": 0.3,
        "tolerance_plus": 0.3
      }
    }
  ]
}
```

#### 2. **GET** `/products/{productId}`
Get product detail by product_id.

#### 3. **GET** `/products`
Get products list dengan pagination.

**Query Parameters:**
- `page`: Page number (default: 1)
- `limit`: Items per page (default: 10, max: 100)
- `product_category_id`: Filter by category
- `query`: Search by product name, article code, atau product ID

#### 4. **GET** `/products/is-product-exists`
Check if product already exists dengan basic info yang sama.

#### 5. **GET** `/products/categories`
Get list of product categories.

### Product Measurement Endpoints

#### 1. **POST** `/product-measurement/{measurementId}/submit`
Submit measurement results untuk evaluasi.

**Request Body:**
```json
{
  "measurement_results": [
    {
      "measurement_item_name_id": "inside_diameter",
      "variable_values": [
        {
          "name_id": "room_temp",
          "value": 25.5
        }
      ],
      "samples": [
        {
          "sample_index": 1,
          "single_value": 14.2
        },
        {
          "sample_index": 2, 
          "single_value": 14.3
        }
      ]
    }
  ]
}
```

#### 2. **GET** `/product-measurement/{measurementId}`
Get measurement details dan results.

## 🏭 Backend Implementation Details

### Models & Relationships
```php
// Model Quarter
class Quarter extends Model {
    // Q1: Jan-Mar, Q2: Apr-Jun, Q3: Jul-Sep, Q4: Oct-Dec
    protected $fillable = ['name', 'year', 'start_month', 'end_month', 'start_date', 'end_date', 'is_active'];
    
    public function products() { return $this->hasMany(Product::class); }
    public static function getActiveQuarter() { /* return active quarter */ }
    public static function generateQuartersForYear(int $year) { /* generate Q1-Q4 */ }
}

// Model ProductCategory
class ProductCategory extends Model {
    protected $fillable = ['name', 'products', 'description'];
    protected $casts = ['products' => 'array'];
    
    public function products() { return $this->hasMany(Product::class); }
    public function isValidProductName(string $productName): bool { /* validation */ }
    public static function seedDefaultCategories() { /* seed 3 categories */ }
}

// Model Product  
class Product extends Model {
    protected $fillable = ['product_id', 'quarter_id', 'product_category_id', 'product_name', 
                          'ref_spec_number', 'nom_size_vo', 'article_code', 'no_document', 
                          'no_doc_reference', 'measurement_points'];
    protected $casts = ['measurement_points' => 'array'];
    
    public function quarter() { return $this->belongsTo(Quarter::class); }
    public function productCategory() { return $this->belongsTo(ProductCategory::class); }
    public function productMeasurements() { return $this->hasMany(ProductMeasurement::class); }
    
    public static function checkProductExists(array $basicInfo): bool { /* check duplicate */ }
    public function validateMeasurementPoints(): array { /* validation */ }
    public function getMeasurementPointByNameId(string $nameId): ?array { /* get by name_id */ }
}

// Model ProductMeasurement
class ProductMeasurement extends Model {
    protected $fillable = ['measurement_id', 'product_id', 'batch_number', 'sample_count', 
                          'status', 'overall_result', 'measurement_results', 'measured_by', 'measured_at'];
    protected $casts = ['overall_result' => 'boolean', 'measurement_results' => 'array'];
    
    public function product() { return $this->belongsTo(Product::class); }
    public function measuredBy() { return $this->belongsTo(LoginUser::class, 'measured_by'); }
    
    public function processMeasurementResults(array $data): array { /* main processing logic */ }
}
```

### Controller Logic
```php
// ProductController
class ProductController extends Controller {
    public function store(Request $request) {
        // 1. Validate basic_info + measurement_points
        // 2. Check ProductCategory::isValidProductName()
        // 3. Check Product::checkProductExists() 
        // 4. Get Quarter::getActiveQuarter()
        // 5. Validate measurement points structure
        // 6. Create Product with auto-generated product_id
    }
    
    public function show(string $productId) { /* get by product_id */ }
    public function index(Request $request) { /* paginated list with filters */ }
    public function checkProductExists(Request $request) { /* duplicate check */ }
    public function getProductCategories() { /* list categories */ }
}

// ProductMeasurementController  
class ProductMeasurementController extends Controller {
    public function submitMeasurement(Request $request, string $measurementId) {
        // 1. Find ProductMeasurement by measurement_id
        // 2. Validate measurement_results structure
        // 3. Call ProductMeasurement::processMeasurementResults()
        // 4. Return overall OK/NG status + detailed results
    }
    
    public function show(string $measurementId) { /* get measurement details */ }
}
```

### Validation Logic
```php
// Product creation validation
private function validateMeasurementPoints(array $points): array {
    foreach ($points as $index => $point) {
        // Validate setup (required fields, enum values)
        // Validate variables (unique names, type-specific fields)
        // Validate pre_processing_formulas (unique names)
        // Validate evaluation_type & evaluation_setting
        // Validate rule_evaluation_setting (conditional)
    }
}

// Measurement submission validation  
private function validateMeasurementData(array $data): array {
    // Validate measurement_results array
    // Validate measurement_item_name_id exists in product
    // Validate samples array structure
    // Validate variable_values for MANUAL variables
    // Validate joint_setting_formula_values for JOINT type
}
```

## 🔄 Measurement Submission Flow

### Step 1: Frontend Submission
Ketika user tekan button "Submit Measurement" pada measurement item:

```json
POST /api/v1/product-measurement/{measurement_id}/submit
{
  "measurement_results": [
    {
      "measurement_item_name_id": "inside_diameter",
      "variable_values": [
        {"name_id": "room_temp", "value": 25.5}  // untuk MANUAL variables
      ],
      "samples": [
        {"sample_index": 1, "single_value": 14.2},
        {"sample_index": 2, "single_value": 14.3},
        // ... semua samples
      ],
      "joint_setting_formula_values": [  // hanya untuk JOINT type
        {"name": "AVG_WEIGHT", "value": 10.2, "is_final_value": false},
        {"name": "FORCE", "value": 100.0, "is_final_value": true}
      ]
    }
  ]
}
```

### Step 2: Backend Processing Logic
```php
public function processMeasurementResults(array $data): array {
    $overallStatus = true;
    $processedResults = [];
    
    foreach ($data['measurement_results'] as $item) {
        // 1. Get measurement point configuration dari product
        $measurementPoint = $this->product->getMeasurementPointByNameId($item['measurement_item_name_id']);
        
        // 2. Process samples dengan variables & pre-processing formulas
        $processedSamples = $this->processSamples($item, $measurementPoint);
        
        // 3. Evaluate berdasarkan evaluation_type
        switch ($measurementPoint['evaluation_type']) {
            case 'PER_SAMPLE':
                $result = $this->evaluatePerSample($processedSamples, $measurementPoint);
                break;
            case 'JOINT': 
                $result = $this->evaluateJoint($processedSamples, $item, $measurementPoint);
                break;
            case 'SKIP_CHECK':
                $result['status'] = true;
                break;
        }
        
        // 4. Update overall status
        if (!$result['status']) $overallStatus = false;
        $processedResults[] = $result;
    }
    
    // 5. Update measurement record
    $this->update([
        'measurement_results' => $processedResults,
        'overall_result' => $overallStatus,
        'status' => 'COMPLETED'
    ]);
    
    return ['overall_status' => $overallStatus, 'measurement_results' => $processedResults];
}
```

### Step 3: Formula Processing Detail
```php
private function processSamples(array $item, array $measurementPoint): array {
    $processedSamples = [];
    $variables = $item['variable_values'] ?? [];
    
    foreach ($item['samples'] as $sample) {
        $executor = new MathExecutor();
        
        // Set variables (FIXED, MANUAL)
        foreach ($variables as $var) {
            $executor->setVar($var['name_id'], $var['value']);
        }
        
        // Set raw values 
        if ($measurementPoint['setup']['type'] === 'SINGLE') {
            $executor->setVar('single_value', $sample['single_value']);
        } else {
            $executor->setVar('before', $sample['before_after_value']['before']);
            $executor->setVar('after', $sample['before_after_value']['after']);
        }
        
        // Process pre-processing formulas
        $processedValues = [];
        foreach ($measurementPoint['pre_processing_formulas'] ?? [] as $formula) {
            $result = $executor->execute($formula['formula']);
            $processedValues[$formula['name']] = $result;
            $executor->setVar($formula['name'], $result);  // untuk formula berikutnya
        }
        
        $processedSamples[] = [
            'sample_index' => $sample['sample_index'],
            'raw_values' => $sample,
            'processed_values' => $processedValues
        ];
    }
    
    return $processedSamples;
}
```

### Step 4: Evaluation Logic Detail  
```php
// PER_SAMPLE Evaluation
private function evaluatePerSample(array $samples, array $measurementPoint): array {
    $allOK = true;
    $setting = $measurementPoint['evaluation_setting']['per_sample_setting'];
    $rule = $measurementPoint['rule_evaluation_setting'];
    
    foreach ($samples as &$sample) {
        // Get value untuk evaluasi
        if ($setting['is_raw_data']) {
            $valueToEvaluate = $sample['raw_values']['single_value'];  // atau before_after
        } else {
            $formulaName = $setting['pre_processing_formula_name'];
            $valueToEvaluate = $sample['processed_values'][$formulaName];
        }
        
        // Evaluate dengan rule
        $sampleOK = $this->evaluateWithRule($valueToEvaluate, $rule);
        $sample['status'] = $sampleOK;
        $sample['evaluated_value'] = $valueToEvaluate;
        
        if (!$sampleOK) $allOK = false;
    }
    
    return ['status' => $allOK, 'samples' => $samples];
}

// JOINT Evaluation  
private function evaluateJoint(array $samples, array $item, array $measurementPoint): array {
    $jointResults = $item['joint_setting_formula_values'];
    
    // Find final value
    $finalValue = null;
    foreach ($jointResults as $result) {
        if ($result['is_final_value']) {
            $finalValue = $result['value'];
            break;
        }
    }
    
    // Evaluate final value dengan rule
    $rule = $measurementPoint['rule_evaluation_setting'];
    $status = $this->evaluateWithRule($finalValue, $rule);
    
    return [
        'status' => $status,
        'final_value' => $finalValue,
        'joint_results' => $jointResults
    ];
}

// Rule Evaluation
private function evaluateWithRule($value, array $rule): bool {
    switch ($rule['rule']) {
        case 'MIN': return $value >= $rule['value'];
        case 'MAX': return $value <= $rule['value'];
        case 'BETWEEN':
            $min = $rule['value'] - $rule['tolerance_minus'];
            $max = $rule['value'] + $rule['tolerance_plus'];
            return $value >= $min && $value <= $max;
        default: return false;
    }
}
```

## 🧮 Formula Processing

Sistem menggunakan **NXP MathExecutor** untuk memproses mathematical formulas:

### Supported Operations:
- Basic: `+`, `-`, `*`, `/`, `^` (power)
- Functions: `AVG()`, `SUM()`, `MIN()`, `MAX()`, `ABS()`, `SQRT()`
- Trigonometric: `SIN()`, `COS()`, `TAN()`
- Constants: `PI`, `E`

### Formula Examples:
```javascript
// Basic arithmetic
"THICKNESS_TOTAL = THICKNESS_A + THICKNESS_B + THICKNESS_C"

// Average calculation  
"AVG_THICKNESS = AVG(THICKNESS_A, THICKNESS_B, THICKNESS_C)"

// Complex formula
"CROSS_SECTION = (PI * (DIAMETER / 2)^2) * LENGTH"

// Conditional logic
"RESULT = IF(TEMP > 25, VALUE_A, VALUE_B)"
```

## 🚀 Testing Guide

### 1. Setup Environment
```bash
# Run migrations
php artisan migrate

# Run seeders
php artisan db:seed
```

### 2. Test Authentication
```bash
# Login untuk mendapatkan JWT token
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "password": "admin123"
  }'
```

### 3. Test Product Creation
```bash
curl -X POST http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d @product_example.json
```

### 4. Test Product List
```bash
curl -X GET "http://localhost:8000/api/v1/products?page=1&limit=10" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## 📝 Example Data

### Product Categories seeded:
1. **Tube Test** (ID: 1)
2. **Wire Test Reguler** (ID: 2)  
3. **Shield Wire Test** (ID: 3)

### Active Quarter:
- **Q4 2024** (Oct - Dec 2024)

### Role Access:
- **Admin**: Can CRUD products
- **SuperAdmin**: Can CRUD products  
- **Operator**: View only

## 🔧 Formula Logic Deep Dive

### Step-by-Step Formula Processing:

1. **Variables Processing**: 
   - FIXED variables: Set nilai konstan
   - MANUAL variables: Input saat measurement
   - FORMULA variables: Hitung berdasarkan formula

2. **Pre-Processing per Sample**:
   - Gunakan raw values + variables
   - Jalankan pre-processing formulas
   - Simpan hasil untuk evaluasi

3. **Evaluation**:
   - **PER_SAMPLE**: Evaluasi tiap sample dengan rule
   - **JOINT**: Agregasi semua sample → evaluasi hasil akhir

4. **Rule Evaluation**:
   - MIN: `value >= threshold`
   - MAX: `value <= threshold`  
   - BETWEEN: `min_range <= value <= max_range`

### OK/NG Logic:
- **PER_SAMPLE**: Semua sample harus OK → Measurement item OK
- **JOINT**: Final aggregated value harus OK → Measurement item OK
- **Product Level**: Semua measurement items harus OK → Product OK

## 🧪 Frontend Implementation Guide

### Step-by-Step UI Flow
```javascript
// 1. Get Product Categories
fetch('/api/v1/products/categories')
  .then(response => response.json())
  .then(categories => {
    // Populate category dropdown
    // Filter product names based on selected category
  });

// 2. Dynamic Product Name Filtering
function filterProductNames(categoryId) {
  const category = categories.find(c => c.id === categoryId);
  const productOptions = category.products; // Array of valid product names
  // Update product name dropdown dengan options ini
}

// 3. Add Measurement Point
function addMeasurementPoint() {
  return {
    setup: {
      name: "",
      name_id: "", // auto-generate from name
      sample_amount: 1,
      source: "MANUAL", // dropdown: INSTRUMENT|MANUAL|DERIVED
      type: "SINGLE",   // dropdown: SINGLE|BEFORE_AFTER  
      nature: "QUANTITATIVE" // dropdown: QUALITATIVE|QUANTITATIVE
    },
    variables: [], // initially empty, add via button
    pre_processing_formulas: [], // initially empty, add via button
    evaluation_type: "", // dropdown: PER_SAMPLE|JOINT|SKIP_CHECK
    evaluation_setting: {}, // conditional based on evaluation_type
    rule_evaluation_setting: {} // conditional based on nature
  };
}

// 4. Dynamic Form Validation
function validateMeasurementPoint(point) {
  const errors = [];
  
  // Validate setup required fields
  if (!point.setup.name) errors.push("Setup name required");
  if (!point.setup.sample_amount || point.setup.sample_amount < 1) 
    errors.push("Sample amount must be >= 1");
  
  // Validate source-specific fields
  if (point.setup.source === 'INSTRUMENT' && !point.setup.source_instrument_id)
    errors.push("Instrument ID required for INSTRUMENT source");
  if (point.setup.source === 'DERIVED' && !point.setup.source_derived_name_id)
    errors.push("Source derived required for DERIVED source");
    
  // Validate variables uniqueness
  const varNames = point.variables.map(v => v.name);
  if (new Set(varNames).size !== varNames.length)
    errors.push("Variable names must be unique");
    
  // Validate evaluation setting based on type
  if (point.evaluation_type === 'PER_SAMPLE') {
    const setting = point.evaluation_setting.per_sample_setting;
    if (!setting?.is_raw_data && !setting?.pre_processing_formula_name)
      errors.push("Per sample setting: choose raw data OR formula");
  }
  
  return errors;
}

// 5. Measurement Submission
function submitMeasurement(measurementId, data) {
  return fetch(`/api/v1/product-measurement/${measurementId}/submit`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      measurement_results: [
        {
          measurement_item_name_id: "inside_diameter",
          variable_values: [
            {name_id: "room_temp", value: 25.5} // untuk MANUAL variables
          ],
          samples: [
            {sample_index: 1, single_value: 14.2},
            {sample_index: 2, single_value: 14.3}
            // ... semua samples sesuai sample_amount
          ],
          joint_setting_formula_values: [ // hanya untuk JOINT
            {name: "FORCE", value: 100.0, is_final_value: true}
          ]
        }
      ]
    })
  });
}
```

### UI Components Recommendation
```jsx
// React component structure
<ProductForm>
  <BasicInfoStep />
  <MeasurementPointsStep>
    {measurementPoints.map(point => (
      <MeasurementPointForm key={point.id}>
        <SetupStep />
        <VariablesStep optional />
        <PreProcessingStep optional />
        <EvaluationTypeStep />
        <EvaluationSettingStep conditional />
        <RuleEvaluationStep conditional />
      </MeasurementPointForm>
    ))}
  </MeasurementPointsStep>
</ProductForm>

// Measurement submission
<MeasurementSubmissionForm product={product}>
  {product.measurement_points.map(point => (
    <MeasurementItemForm key={point.setup.name_id}>
      <SampleInputs type={point.setup.type} amount={point.setup.sample_amount} />
      <VariableInputs variables={point.variables.filter(v => v.type === 'MANUAL')} />
      {point.evaluation_type === 'JOINT' && 
        <JointFormulaInputs formulas={point.evaluation_setting.joint_setting.formulas} />
      }
      <SubmitButton />
    </MeasurementItemForm>
  ))}
</MeasurementSubmissionForm>
```

## 🔧 Testing Implementation

### Unit Tests Structure
```php
// tests/Feature/ProductTest.php
class ProductTest extends TestCase {
    public function test_can_create_simple_product() { /* basic product creation */ }
    public function test_can_create_complex_product_with_formulas() { /* with variables & formulas */ }
    public function test_validation_error_on_invalid_data() { /* validation testing */ }
    public function test_product_category_validation() { /* category-product name validation */ }
    public function test_quarter_relationship() { /* auto-assign active quarter */ }
    public function test_auto_generate_product_id() { /* PRD-XXXXXXXX format */ }
}

// tests/Feature/ProductMeasurementTest.php  
class ProductMeasurementTest extends TestCase {
    public function test_can_submit_per_sample_measurement() { /* PER_SAMPLE flow */ }
    public function test_can_submit_joint_measurement() { /* JOINT flow */ }
    public function test_formula_processing_works() { /* mathematical formulas */ }
    public function test_ok_ng_evaluation_logic() { /* rule evaluation */ }
    public function test_complex_measurement_with_variables() { /* full flow */ }
}
```

### Postman Testing Collection
```json
{
  "name": "SyncFlow Product Management",
  "requests": [
    {
      "name": "1. Login Admin",
      "method": "POST",
      "url": "{{base_url}}/api/v1/login",
      "body": {"username": "admin", "password": "admin123"}
    },
    {
      "name": "2. Get Product Categories", 
      "method": "GET",
      "url": "{{base_url}}/api/v1/products/categories"
    },
    {
      "name": "3. Create Simple Product",
      "method": "POST", 
      "url": "{{base_url}}/api/v1/products",
      "body": "/* Example dari PRODUCT_TESTING_EXAMPLES.md */"
    },
    {
      "name": "4. Create Complex Product with Formulas",
      "method": "POST",
      "url": "{{base_url}}/api/v1/products", 
      "body": "/* Complex example dengan variables & formulas */"
    },
    {
      "name": "5. Submit Measurement - PER_SAMPLE",
      "method": "POST",
      "url": "{{base_url}}/api/v1/product-measurement/{{measurement_id}}/submit"
    },
    {
      "name": "6. Submit Measurement - JOINT",
      "method": "POST", 
      "url": "{{base_url}}/api/v1/product-measurement/{{measurement_id}}/submit"
    }
  ]
}
```

## 🛠️ Development Notes

### Key Implementation Files:
```
app/Models/
├── Quarter.php                    # Q1-Q4 management
├── ProductCategory.php            # Hierarchical categories  
├── Product.php                    # Main product with measurement_points JSON
└── ProductMeasurement.php         # Measurement processing & evaluation

app/Http/Controllers/Api/V1/
├── ProductController.php          # Product CRUD + validation
└── ProductMeasurementController.php # Measurement submission & processing

database/migrations/
├── 2025_09_24_155851_create_quarters_table.php
├── 2025_09_24_155933_create_product_categories_table.php  
├── 2025_09_24_160021_create_products_table.php
└── 2025_09_24_160110_create_product_measurements_table.php

database/seeders/
├── QuarterSeeder.php              # Generate Q1-Q4 for years
└── ProductCategorySeeder.php      # Seed 3 main categories

tests/Feature/
├── ProductTest.php                # Product functionality tests
└── ProductMeasurementTest.php     # Measurement processing tests
```

### Technology Stack:
- **Laravel 11** dengan PHP 8.3
- **MySQL** dengan JSON fields untuk complex data
- **JWT Authentication** (tymon/jwt-auth)
- **NXP MathExecutor** untuk formula processing
- **Spatie Query Builder** untuk advanced filtering

### Performance Considerations:
- JSON fields untuk measurement_points (flexible structure)
- Indexes pada product_id, quarter_id, category_id
- Pagination untuk product list
- Eager loading untuk relationships
- Validation caching untuk complex rules

### Security Features:
- Role-based access (Admin/SuperAdmin untuk CRUD products)
- JWT token authentication
- Input validation & sanitization
- SQL injection prevention
- CORS configuration untuk frontend

### Future Enhancements:
- [ ] Real-time IoT integration untuk INSTRUMENT source
- [ ] Batch measurement processing  
- [ ] Advanced reporting dashboard
- [ ] Export ke Excel/PDF
- [ ] Audit trail logging
- [ ] Advanced formula validation
- [ ] Mobile app support
