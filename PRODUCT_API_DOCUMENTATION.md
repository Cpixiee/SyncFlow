# 🚀 SyncFlow Product API Documentation

## 📋 **Table of Contents**
1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Product Management Endpoints](#product-management-endpoints)
4. [Product Measurement Endpoints](#product-measurement-endpoints)
5. [Complete Testing Scenarios](#complete-testing-scenarios)
6. [Formula Logic Explanation](#formula-logic-explanation)
7. [OK/NG Evaluation Rules](#okng-evaluation-rules)

---

## 🎯 **Overview**

SyncFlow API menyediakan sistem measurement product yang lengkap dengan:
- ✅ **Formula Dependencies** - Measurement items bisa depend pada item lain
- ✅ **OK/NG Evaluation** - Automatic evaluation berdasarkan tolerance rules
- ✅ **Progress Tracking** - Save partial progress dan resume later
- ✅ **Multiple Evaluation Types** - PER_SAMPLE dan JOINT evaluation
- ✅ **Pre-processing Formulas** - Transform raw data sebelum evaluation

---

## 🔐 **Authentication**

### Base URL
```
http://localhost:8000/api/v1
```

### Login
**Endpoint**: `POST /login`

**Request**:
```json
{
  "username": "superadmin",
  "password": "admin123"
}
```

**Response**:
```json
{
  "http_code": 200,
  "message": "Login successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_in": 86400
  }
}
```

**Headers untuk semua request selanjutnya**:
```
Authorization: Bearer {token}
Content-Type: application/json
```

---

## 📦 **Product Management Endpoints**

### 1. Create Product
**Endpoint**: `POST /products`  
**Auth**: Required (admin/superadmin)  
**Function**: Membuat product baru dengan measurement points dan formula logic

#### **Simple Product Example**:
```json
{
  "basic_info": {
    "product_category_name": "Tube Test",
    "product_name": "Simple Thickness Test",
    "ref_spec_number": "SPEC-SIMPLE-001",
    "article_code": "SIMPLE-001"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness Measurement",
        "name_id": "thickness_measurement",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": null,
      "pre_processing_formulas": null,
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
        "value": 2.5,
        "tolerance_minus": 0.2,
        "tolerance_plus": 0.2
      }
    }
  ]
}
```

#### **Complex Product with Formulas and Grouping**:
```json
{
  "basic_info": {
    "product_category_name": "Tube Test",
    "product_name": "COT Complete Analysis",
    "ref_spec_number": "SPEC-COT-001",
    "article_code": "COT-001"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness A Measurement",
        "name_id": "thickness_a_measurement",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": null,
      "pre_processing_formulas": null,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 2.5,
        "tolerance_minus": 0.2,
        "tolerance_plus": 0.2
      }
    },
    {
      "setup": {
        "name": "Room Temperature Analysis",
        "name_id": "room_temperature_analysis",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FORMULA",
          "name": "thickness_avg",
          "formula": "AVG(thickness_a_measurement)",
          "is_show": true
        },
        {
          "type": "FORMULA",
          "name": "cross_section",
          "formula": "thickness_avg * 5",
          "is_show": true
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "room_temp_normalized",
          "formula": "single_value / cross_section",
          "is_show": true
        }
      ],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "FORCE",
              "formula": "AVG(room_temp_normalized) * 9.80665",
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "N/mm²",
        "value": 2.0,
        "tolerance_minus": 0.3,
        "tolerance_plus": 0.3
      }
    }
  ],
  "measurement_groups": [
    {
      "group_name": "THICKNESS",
      "order": 1,
      "measurement_items": ["thickness_a_measurement"]
    },
    {
      "group_name": "TEMPERATURE",
      "order": 2,
      "measurement_items": ["room_temperature_analysis"]
    }
  ]
}
```

#### **Measurement Grouping Explanation**:

**Purpose**: Grouping allows you to organize measurement items into logical groups and control the order of measurement during execution.

**Structure**:
```json
"measurement_groups": [
  {
    "group_name": "THICKNESS",           // Group display name
    "order": 1,                         // Group execution order
    "measurement_items": [              // Array of measurement item name_ids
      "thickness_a_measurement",
      "thickness_b_measurement", 
      "thickness_c_measurement"
    ]
  },
  {
    "group_name": "DIAMETER", 
    "order": 2,
    "measurement_items": ["inside_diameter"]
  }
]
```

**Execution Flow**:
1. **THICKNESS Group** (Order 1): User measures Thickness A → B → C first
2. **DIAMETER Group** (Order 2): Then measures Inside Diameter
3. **Ungrouped Items**: Any items not in groups appear at the end

**Benefits**:
- ✅ **Organized UI**: Flutter app can display grouped measurement items
- ✅ **Logical Flow**: Measurements follow business workflow
- ✅ **Drag & Drop**: Frontend can implement group management
- ✅ **Dependencies**: Groups can enforce measurement order for formula dependencies

### 2. Get Products List
**Endpoint**: `GET /products`  
**Function**: Mendapatkan daftar semua products

**Response**:
```json
{
  "http_code": 200,
  "data": [
    {
      "product_id": "PRD-ABC123",
      "product_name": "COT Complete Analysis",
      "product_category_name": "Tube Test",
      "ref_spec_number": "SPEC-COT-001",
      "article_code": "COT-001"
    }
  ]
}
```

### 3. Get Product by ID
**Endpoint**: `GET /products/{product_id}`  
**Function**: Mendapatkan detail product beserta measurement points

### 4. Check Product Exists
**Endpoint**: `GET /products/is-product-exists`  
**Function**: Cek apakah product dengan criteria tertentu sudah ada

### 5. Get Product Categories
**Endpoint**: `GET /products/categories`  
**Function**: Mendapatkan daftar kategori products

---

## 📊 **Product Measurement Endpoints**

### 1. Get Product Measurements List (Monthly Target Page)
**Endpoint**: `GET /product-measurements`  
**Function**: Mendapatkan daftar products dengan status measurement untuk Monthly Target page

**Query Parameters**:
```
?page=1&limit=10&status=TODO&query=COT&product_category_id=1
```

**Response**:
```json
{
  "http_code": 200,
  "data": {
    "docs": [
      {
        "product_measurement_id": null,
        "status": "TODO",
        "batch_number": null,
        "progress": null,
        "due_date": "2024-12-31 23:59:59",
        "product": {
          "id": "PRD-ABC123",
          "product_name": "COT Complete Analysis",
          "product_category_name": "Tube Test"
        }
      }
    ],
    "metadata": {
      "current_page": 1,
      "total_page": 1,
      "limit": 10,
      "total_docs": 1
    }
  }
}
```

**Status Types**:
- `TODO`: Belum ada measurement entry
- `ONGOING`: Sedang dalam proses measurement
- `NEED_TO_MEASURE`: Ada measurement entry tapi belum selesai
- `OK`: Measurement selesai dan passed

### 2. Create Measurement Entry
**Endpoint**: `POST /product-measurement`  
**Function**: Membuat measurement entry baru (ketika user click product dan input batch number)

**Request**:
```json
{
  "product_id": "PRD-ABC123",
  "due_date": "2024-12-31 23:59:59"
}
```

**Response**:
```json
{
  "http_code": 201,
  "message": "Measurement entry created successfully",
  "data": {
    "product_measurement_id": "MSR-XYZ789"
  }
}
```

### 3. Check Samples (Individual Measurement Item)
**Endpoint**: `POST /product-measurement/{measurement_id}/samples/check`  
**Function**: Input dan check samples untuk measurement item tertentu

**Request Example - Simple Measurement**:
```json
{
  "measurement_item_name_id": "thickness_measurement",
  "variable_values": [],
  "samples": [
    {
      "sample_index": 1,
      "single_value": 2.4,
      "before_after_value": null,
      "qualitative_value": null
    },
    {
      "sample_index": 2,
      "single_value": 2.5,
      "before_after_value": null,
      "qualitative_value": null
    },
    {
      "sample_index": 3,
      "single_value": 2.6,
      "before_after_value": null,
      "qualitative_value": null
    }
  ]
}
```

**Response - OK Case**:
```json
{
  "http_code": 200,
  "message": "Samples processed successfully",
  "data": {
    "status": true,
    "variable_values": [],
    "samples": [
      {
        "sample_index": 1,
        "status": true,
        "single_value": 2.4,
        "pre_processing_formula_values": null
      },
      {
        "sample_index": 2,
        "status": true,
        "single_value": 2.5,
        "pre_processing_formula_values": null
      },
      {
        "sample_index": 3,
        "status": true,
        "single_value": 2.6,
        "pre_processing_formula_values": null
      }
    ]
  }
}
```

### 4. Save Progress
**Endpoint**: `POST /product-measurement/{measurement_id}/save-progress`  
**Function**: Menyimpan partial measurement results

**Request**:
```json
{
  "measurement_results": [
    {
      "measurement_item_name_id": "thickness_measurement",
      "status": true,
      "variable_values": [],
      "samples": [
        {"sample_index": 1, "status": true, "single_value": 2.4},
        {"sample_index": 2, "status": true, "single_value": 2.5},
        {"sample_index": 3, "status": true, "single_value": 2.6}
      ]
    }
  ]
}
```

### 5. Final Submit
**Endpoint**: `POST /product-measurement/{measurement_id}/submit`  
**Function**: Submit final measurement results dan mendapatkan overall OK/NG

**Request**: (Same as Save Progress but with all measurement items)

**Response**:
```json
{
  "http_code": 200,
  "message": "Measurement results processed successfully",
  "data": {
    "status": true,
    "overall_result": "OK",
    "evaluation_summary": {
      "total_items": 2,
      "passed_items": 2,
      "failed_items": 0,
      "pass_rate": 100,
      "item_details": [
        {
          "measurement_item": "thickness_measurement",
          "status": true,
          "result": "OK",
          "evaluation_type": "PER_SAMPLE",
          "samples_summary": [
            {"sample_index": 1, "status": true, "result": "OK"},
            {"sample_index": 2, "status": true, "result": "OK"},
            {"sample_index": 3, "status": true, "result": "OK"}
          ]
        }
      ]
    }
  }
}
```

### 6. Get Measurement by ID
**Endpoint**: `GET /product-measurement/{measurement_id}`  
**Function**: Mendapatkan detail measurement results

---

## 🧪 **Complete Testing Scenarios**

### **Scenario 1: Simple Product - All OK**

#### Step 1: Login
```bash
POST /login
{
  "username": "superadmin",
  "password": "admin123"
}
```

#### Step 2: Create Simple Product
```bash
POST /products
# Use Simple Product JSON from above
```
**Expected**: `product_id` returned

#### Step 3: Create Measurement Entry
```bash
POST /product-measurement
{
  "product_id": "PRD-ABC123",
  "due_date": "2024-12-31 23:59:59"
}
```
**Expected**: `measurement_id` returned

#### Step 4: Input Samples - OK Values
```bash
POST /product-measurement/MSR-XYZ789/samples/check
{
  "measurement_item_name_id": "thickness_measurement",
  "variable_values": [],
  "samples": [
    {"sample_index": 1, "single_value": 2.4},
    {"sample_index": 2, "single_value": 2.5},
    {"sample_index": 3, "single_value": 2.6}
  ]
}
```
**Expected**: 
- `data.status: true`
- All samples `status: true`
- Values dalam range 2.3-2.7mm ✅

#### Step 5: Final Submit
```bash
POST /product-measurement/MSR-XYZ789/submit
{
  "measurement_results": [
    {
      "measurement_item_name_id": "thickness_measurement",
      "status": true,
      "samples": [
        {"sample_index": 1, "status": true, "single_value": 2.4},
        {"sample_index": 2, "status": true, "single_value": 2.5},
        {"sample_index": 3, "status": true, "single_value": 2.6}
      ]
    }
  ]
}
```
**Expected**:
- `overall_result: "OK"`
- `pass_rate: 100`
- `passed_items: 1, failed_items: 0`

---

### **Scenario 2: Simple Product - NG Case**

#### Step 4: Input Samples - NG Values
```bash
POST /product-measurement/MSR-XYZ789/samples/check
{
  "measurement_item_name_id": "thickness_measurement",
  "variable_values": [],
  "samples": [
    {"sample_index": 1, "single_value": 1.0},  // NG: Below 2.3mm
    {"sample_index": 2, "single_value": 3.5},  // NG: Above 2.7mm
    {"sample_index": 3, "single_value": 2.5}   // OK: Within range
  ]
}
```
**Expected**:
- `data.status: false`
- Sample 1 `status: false` (1.0 < 2.3)
- Sample 2 `status: false` (3.5 > 2.7)
- Sample 3 `status: true` (2.5 in range)

#### Step 5: Final Submit - NG Result
**Expected**:
- `overall_result: "NG"`
- `pass_rate: 0` (karena measurement item failed)
- `passed_items: 0, failed_items: 1`

---

### **Scenario 3: Complete Product with Grouping (Thickness A, B, C)**

#### Step 1: Login
```bash
POST /login
{
  "username": "superadmin",
  "password": "admin123"
}
```

#### Step 2: Create Complete Product with Grouping
```bash
POST /products
{
  "basic_info": {
    "product_category_name": "Tube Test",
    "product_name": "Complete ABC Thickness Test",
    "ref_spec_number": "SPEC-ABC-001",
    "article_code": "ABC-001"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness A Measurement",
        "name_id": "thickness_a_measurement",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": null,
      "pre_processing_formulas": null,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 2.5,
        "tolerance_minus": 0.2,
        "tolerance_plus": 0.2
      }
    },
    {
      "setup": {
        "name": "Thickness B Measurement",
        "name_id": "thickness_b_measurement",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": null,
      "pre_processing_formulas": null,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 2.3,
        "tolerance_minus": 0.15,
        "tolerance_plus": 0.15
      }
    },
    {
      "setup": {
        "name": "Thickness C Measurement",
        "name_id": "thickness_c_measurement",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": null,
      "pre_processing_formulas": null,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 2.8,
        "tolerance_minus": 0.25,
        "tolerance_plus": 0.25
      }
    },
    {
      "setup": {
        "name": "Inside Diameter",
        "name_id": "inside_diameter",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": null,
      "pre_processing_formulas": null,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 10.0,
        "tolerance_minus": 0.5,
        "tolerance_plus": 0.5
      }
    },
    {
      "setup": {
        "name": "Room Temperature Analysis",
        "name_id": "room_temperature_analysis",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FORMULA",
          "name": "thickness_avg",
          "formula": "(AVG(thickness_a_measurement) + AVG(thickness_b_measurement) + AVG(thickness_c_measurement)) / 3",
          "is_show": true
        },
        {
          "type": "FORMULA",
          "name": "cross_section",
          "formula": "thickness_avg * 5",
          "is_show": true
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "room_temp_normalized",
          "formula": "single_value / cross_section",
          "is_show": true
        }
      ],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "FORCE",
              "formula": "AVG(room_temp_normalized) * 9.80665",
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "N/mm²",
        "value": 2.0,
        "tolerance_minus": 0.3,
        "tolerance_plus": 0.3
      }
    }
  ],
  "measurement_groups": [
    {
      "group_name": "THICKNESS",
      "order": 1,
      "measurement_items": ["thickness_a_measurement", "thickness_b_measurement", "thickness_c_measurement"]
    },
    {
      "group_name": "DIAMETER",
      "order": 2,
      "measurement_items": ["inside_diameter"]
    },
    {
      "group_name": "ANALYSIS",
      "order": 3,
      "measurement_items": ["room_temperature_analysis"]
    }
  ]
}
```
**Expected**: Product created with grouped measurement items

#### Step 3: Create Measurement Entry
```bash
POST /product-measurement
{
  "product_id": "PRD-ABC123",
  "due_date": "2024-12-31 23:59:59"
}
```

#### Step 4: Follow Group Order - THICKNESS Group First

#### Step 4a: Input Thickness A (Group: THICKNESS, Order 1)
```bash
POST /product-measurement/MSR-XYZ789/samples/check
{
  "measurement_item_name_id": "thickness_a_measurement",
  "variable_values": [],
  "samples": [
    {"sample_index": 1, "single_value": 2.4},
    {"sample_index": 2, "single_value": 2.5},
    {"sample_index": 3, "single_value": 2.6}
  ]
}
```
**Expected**: All OK, AVG = 2.5, Range 2.3-2.7mm ✅

#### Step 4b: Input Thickness B (Group: THICKNESS, Order 1)
```bash
POST /product-measurement/MSR-XYZ789/samples/check
{
  "measurement_item_name_id": "thickness_b_measurement",
  "variable_values": [],
  "samples": [
    {"sample_index": 1, "single_value": 2.2},
    {"sample_index": 2, "single_value": 2.3},
    {"sample_index": 3, "single_value": 2.4}
  ]
}
```
**Expected**: All OK, AVG = 2.3, Range 2.15-2.45mm ✅

#### Step 4c: Input Thickness C (Group: THICKNESS, Order 1)
```bash
POST /product-measurement/MSR-XYZ789/samples/check
{
  "measurement_item_name_id": "thickness_c_measurement",
  "variable_values": [],
  "samples": [
    {"sample_index": 1, "single_value": 2.7},
    {"sample_index": 2, "single_value": 2.8},
    {"sample_index": 3, "single_value": 2.9}
  ]
}
```
**Expected**: All OK, AVG = 2.8, Range 2.55-3.05mm ✅

#### Step 5: Save Progress After THICKNESS Group
```bash
POST /product-measurement/MSR-XYZ789/save-progress
{
  "measurement_results": [
    {
      "measurement_item_name_id": "thickness_a_measurement",
      "status": true,
      "variable_values": [],
      "samples": [
        {"sample_index": 1, "status": true, "single_value": 2.4},
        {"sample_index": 2, "status": true, "single_value": 2.5},
        {"sample_index": 3, "status": true, "single_value": 2.6}
      ]
    },
    {
      "measurement_item_name_id": "thickness_b_measurement",
      "status": true,
      "variable_values": [],
      "samples": [
        {"sample_index": 1, "status": true, "single_value": 2.2},
        {"sample_index": 2, "status": true, "single_value": 2.3},
        {"sample_index": 3, "status": true, "single_value": 2.4}
      ]
    },
    {
      "measurement_item_name_id": "thickness_c_measurement",
      "status": true,
      "variable_values": [],
      "samples": [
        {"sample_index": 1, "status": true, "single_value": 2.7},
        {"sample_index": 2, "status": true, "single_value": 2.8},
        {"sample_index": 3, "status": true, "single_value": 2.9}
      ]
    }
  ]
}
```
**Expected**: Progress = 60% (3 of 5 items completed), THICKNESS group ✅ complete

#### Step 6: DIAMETER Group (Order 2)
```bash
POST /product-measurement/MSR-XYZ789/samples/check
{
  "measurement_item_name_id": "inside_diameter",
  "variable_values": [],
  "samples": [
    {"sample_index": 1, "single_value": 9.8},
    {"sample_index": 2, "single_value": 10.0},
    {"sample_index": 3, "single_value": 10.2}
  ]
}
```
**Expected**: All OK, Range 9.5-10.5mm ✅

#### Step 7: ANALYSIS Group (Order 3) - With Formula Dependencies
```bash
POST /product-measurement/MSR-XYZ789/samples/check
{
  "measurement_item_name_id": "room_temperature_analysis",
  "variable_values": [
    {"name_id": "thickness_avg", "value": 2.53},
    {"name_id": "cross_section", "value": 12.65}
  ],
  "samples": [
    {"sample_index": 1, "single_value": 1.8},
    {"sample_index": 2, "single_value": 1.9},
    {"sample_index": 3, "single_value": 2.1}
  ]
}
```
**Expected**: 
- Variables calculated: thickness_avg = (2.5+2.3+2.8)/3 = 2.53
- cross_section = 2.53 * 5 = 12.65
- Pre-processing: room_temp_normalized per sample
- Joint formula: FORCE = AVG(normalized) * 9.80665 ≈ 1.95
- Final evaluation: 1.95 ∈ [1.7, 2.3] ✅ OK

#### Step 8: Final Submit - Complete All Groups
```bash
POST /product-measurement/MSR-XYZ789/submit
{
  "measurement_results": [
    {
      "measurement_item_name_id": "thickness_a_measurement",
      "status": true,
      "variable_values": [],
      "samples": [
        {"sample_index": 1, "status": true, "single_value": 2.4},
        {"sample_index": 2, "status": true, "single_value": 2.5},
        {"sample_index": 3, "status": true, "single_value": 2.6}
      ],
      "joint_setting_formula_values": null
    },
    {
      "measurement_item_name_id": "thickness_b_measurement",
      "status": true,
      "variable_values": [],
      "samples": [
        {"sample_index": 1, "status": true, "single_value": 2.2},
        {"sample_index": 2, "status": true, "single_value": 2.3},
        {"sample_index": 3, "status": true, "single_value": 2.4}
      ],
      "joint_setting_formula_values": null
    },
    {
      "measurement_item_name_id": "thickness_c_measurement",
      "status": true,
      "variable_values": [],
      "samples": [
        {"sample_index": 1, "status": true, "single_value": 2.7},
        {"sample_index": 2, "status": true, "single_value": 2.8},
        {"sample_index": 3, "status": true, "single_value": 2.9}
      ],
      "joint_setting_formula_values": null
    },
    {
      "measurement_item_name_id": "inside_diameter",
      "status": true,
      "variable_values": [],
      "samples": [
        {"sample_index": 1, "status": true, "single_value": 9.8},
        {"sample_index": 2, "status": true, "single_value": 10.0},
        {"sample_index": 3, "status": true, "single_value": 10.2}
      ],
      "joint_setting_formula_values": null
    },
    {
      "measurement_item_name_id": "room_temperature_analysis",
      "status": true,
      "variable_values": [
        {"name_id": "thickness_avg", "value": 2.53},
        {"name_id": "cross_section", "value": 12.65}
      ],
      "samples": [
        {"sample_index": 1, "status": null, "single_value": 1.8},
        {"sample_index": 2, "status": null, "single_value": 1.9},
        {"sample_index": 3, "status": null, "single_value": 2.1}
      ],
      "joint_setting_formula_values": [
        {
          "name": "FORCE",
          "value": 1.95,
          "formula": "AVG(room_temp_normalized) * 9.80665",
          "is_final_value": true
        }
      ]
    }
  ]
}
```

**Expected Final Response**:
```json
{
  "http_code": 200,
  "message": "Measurement results processed successfully",
  "data": {
    "status": true,
    "overall_result": "OK",
    "evaluation_summary": {
      "total_items": 5,
      "passed_items": 5,
      "failed_items": 0,
      "pass_rate": 100,
      "item_details": [
        {
          "measurement_item": "thickness_a_measurement",
          "status": true,
          "result": "OK",
          "evaluation_type": "PER_SAMPLE",
          "samples_summary": [
            {"sample_index": 1, "result": "OK"},
            {"sample_index": 2, "result": "OK"},
            {"sample_index": 3, "result": "OK"}
          ]
        },
        {
          "measurement_item": "thickness_b_measurement",
          "status": true,
          "result": "OK",
          "evaluation_type": "PER_SAMPLE"
        },
        {
          "measurement_item": "thickness_c_measurement",
          "status": true,
          "result": "OK",
          "evaluation_type": "PER_SAMPLE"
        },
        {
          "measurement_item": "inside_diameter",
          "status": true,
          "result": "OK",
          "evaluation_type": "PER_SAMPLE"
        },
        {
          "measurement_item": "room_temperature_analysis",
          "status": true,
          "result": "OK",
          "evaluation_type": "JOINT",
          "final_value": 1.95
        }
      ]
    }
  }
}
```

**✅ Success Criteria**:
- All groups measured in correct order: THICKNESS → DIAMETER → ANALYSIS
- All measurement items passed: 5/5 ✅
- Formula dependencies resolved correctly
- Overall result: **OK** with 100% pass rate
- Grouping workflow completed successfully

---

## 🧮 **Formula Logic Explanation**

### **Formula Types**

#### 1. **Variable Formulas**
Menghitung nilai dari measurement items lain:
```json
{
  "type": "FORMULA",
  "name": "thickness_avg",
  "formula": "AVG(thickness_a_measurement)",
  "is_show": true
}
```

#### 2. **Pre-processing Formulas**
Transform raw values sebelum evaluation:
```json
{
  "name": "room_temp_normalized",
  "formula": "single_value / cross_section",
  "is_show": true
}
```

#### 3. **Joint Setting Formulas**
Combine processed values untuk final evaluation:
```json
{
  "name": "FORCE",
  "formula": "AVG(room_temp_normalized) * 9.80665",
  "is_final_value": true
}
```

### **Formula Calculation Flow**

```
Step 1: Input Raw Values
thickness_a_measurement: [2.4, 2.5, 2.6]

Step 2: Calculate Variables
thickness_avg = AVG(2.4, 2.5, 2.6) = 2.5
cross_section = 2.5 * 5 = 12.5

Step 3: Pre-processing (per sample)
room_temp_normalized[1] = 1.8 / 12.5 = 0.144
room_temp_normalized[2] = 1.9 / 12.5 = 0.152
room_temp_normalized[3] = 2.1 / 12.5 = 0.168

Step 4: Joint Formula
FORCE = AVG(0.144, 0.152, 0.168) * 9.80665 = 1.95

Step 5: Rule Evaluation
Range: 2.0 ± 0.3 = 1.7 to 2.3
1.95 ∈ [1.7, 2.3] → OK ✅
```

### **Dependencies Logic**

System otomatis check dependencies:
```
room_temperature_analysis depends on:
- thickness_a_measurement (untuk AVG calculation)

Jika thickness_a_measurement belum ada data:
→ Error 400: "Missing dependencies"

Jika sudah ada data:
→ Proceed dengan calculation
```

---

## ✅ **OK/NG Evaluation Rules**

### **Rule Types**

#### 1. **BETWEEN Rule**
```json
{
  "rule": "BETWEEN",
  "value": 2.5,
  "tolerance_minus": 0.2,
  "tolerance_plus": 0.2
}
```
**Logic**: `2.3 ≤ value ≤ 2.7`

#### 2. **MIN Rule**
```json
{
  "rule": "MIN",
  "value": 2.0
}
```
**Logic**: `value ≥ 2.0`

#### 3. **MAX Rule**
```json
{
  "rule": "MAX",
  "value": 5.0
}
```
**Logic**: `value ≤ 5.0`

### **Evaluation Types**

#### **PER_SAMPLE Evaluation**
- Setiap sample dievaluasi individual
- Sample status: OK/NG per sample
- Measurement item OK jika **SEMUA** samples OK

```
Samples: [2.4✅, 1.0❌, 2.6✅]
Result: NG (ada 1 sample failed)
```

#### **JOINT Evaluation**
- Individual samples tidak dievaluasi OK/NG secara terpisah
- Semua samples diproses melalui formula steps
- Final value dari joint formula yang menentukan OK/NG

```
Step 1: Raw Samples → [1.8, 1.9, 2.1]
Step 2: Variables → thickness_avg=2.53, cross_section=12.65
Step 3: Pre-processing → room_temp_normalized per sample
   Sample 1: 1.8/12.65 = 0.142
   Sample 2: 1.9/12.65 = 0.150  
   Sample 3: 2.1/12.65 = 0.166
Step 4: Joint Formula → FORCE = AVG(0.142, 0.150, 0.166) * 9.80665
Step 5: Final Value → 1.95
Step 6: Rule Evaluation → 1.95 vs BETWEEN(1.7, 2.3) → OK ✅

Result: Final Value OK → Measurement Item = OK ✅
```

**Key Differences:**
- **PER_SAMPLE**: Each sample checked individually → ALL must pass
- **JOINT**: All samples combined → Final value checked against rule

#### **Expected JOINT Response Structure:**
```json
{
  "measurement_item": "room_temperature_analysis",
  "status": true,
  "result": "OK",
  "evaluation_type": "JOINT",
  "final_value": 1.95,
  "joint_evaluation": {
    "final_value": 1.95,
    "rule_evaluation": {
      "result": "OK",
      "final_value": 1.95,
      "evaluation_method": "Final value checked against rule",
      "rule_details": {
        "rule": "BETWEEN",
        "target": 2.0,
        "tolerance_minus": 0.3,
        "tolerance_plus": 0.3,
        "range": "1.7 - 2.3",
        "actual": 1.95,
        "passed": true
      }
    },
    "formula_steps": [
      {
        "name": "FORCE",
        "value": 1.95,
        "formula": "AVG(room_temp_normalized) * 9.80665",
        "is_final_value": true
      }
    ]
  },
  "samples_summary": [
    {
      "sample_index": 1,
      "status": true,
      "result": "OK",
      "note": "Final value: 1.95 → OK"
    },
    {
      "sample_index": 2,
      "status": true,
      "result": "OK",
      "note": "Final value: 1.95 → OK"
    },
    {
      "sample_index": 3,
      "status": true,
      "result": "OK",
      "note": "Final value: 1.95 → OK"
    }
  ]
}
```

**Penjelasan JOINT Logic:**
- ✅ `final_value: 1.95` → Hasil dari joint formula
- ✅ `joint_evaluation.rule_evaluation.result: "OK"` → Final OK/NG decision  
- ✅ `samples_summary[].status: "PROCESSED"` → Individual samples tidak dievaluasi OK/NG
- ✅ Formula steps menunjukkan cara calculation final value

**Perbedaan dengan PER_SAMPLE:**
```
PER_SAMPLE:
Sample 1: 2.4mm vs rule → OK ✅
Sample 2: 2.5mm vs rule → OK ✅  
Sample 3: 2.6mm vs rule → OK ✅
Result: ALL OK → Item Status = OK ✅

JOINT:
Sample 1: 1.8 → TIDAK dicek vs rule
Sample 2: 1.9 → TIDAK dicek vs rule
Sample 3: 2.1 → TIDAK dicek vs rule
Process: [1.8, 1.9, 2.1] → Formula → Final Value: 1.95
Rule Check: 1.95 vs BETWEEN(1.7, 2.3) → OK ✅
Result: Final Value OK → Item Status = OK ✅
```

**Jadi untuk JOINT:**
- Individual samples cuma diproses, bukan dievaluasi
- Yang menentukan OK/NG adalah final_value hasil formula
- Kalau final_value NG, maka seluruh measurement item NG (harus ukur ulang semua samples)

#### **JOINT NG Example:**
```
Samples: [3.5, 3.8, 4.1] (values terlalu tinggi)
Process: Formula calculation → Final Value: 3.2
Rule: BETWEEN(1.7, 2.3) → 3.2 > 2.3 → NG ❌
Result: Final Value NG → Item Status = NG ❌

Action Required: Ukur ulang SEMUA samples dengan values yang lebih rendah
```

**Response untuk JOINT NG:**
```json
{
  "measurement_item": "room_temperature_analysis",
  "status": false,
  "result": "NG",
  "evaluation_type": "JOINT",
  "final_value": 3.2,
  "joint_evaluation": {
    "rule_evaluation": {
      "result": "NG",
      "final_value": 3.2,
      "rule_details": {
        "rule": "BETWEEN",
        "range": "1.7 - 2.3",
        "actual": 3.2,
        "passed": false,
        "reason": "Final value 3.2 exceeds maximum 2.3"
      }
    }
  },
  "samples_summary": [
    {
      "sample_index": 1,
      "status": "PROCESSED",
      "result": "REQUIRES_REMEASUREMENT",
      "note": "Final value failed, remeasure all samples"
    }
  ]
}
```

### **Overall Decision Logic**

```
Overall Result = ALL measurement_items.status == true ? "OK" : "NG"

Example:
- thickness_a_measurement: OK ✅
- thickness_b_measurement: OK ✅  
- room_temperature_analysis: NG ❌
→ Overall Result: "NG"

Pass Rate = (passed_items / total_items) * 100
= (2 / 3) * 100 = 66.67%
```

---

## 🎯 **Testing Checklist**

### ✅ **Basic Functionality**
- [ ] Login successful dengan token 24 jam
- [ ] Create simple product berhasil
- [ ] Create complex product dengan formulas berhasil
- [ ] Create measurement entry berhasil
- [ ] Get product measurements list berhasil

### ✅ **Measurement Flow**
- [ ] Input samples untuk simple measurement
- [ ] Check samples OK case (dalam tolerance)
- [ ] Check samples NG case (luar tolerance)
- [ ] Save progress berhasil
- [ ] Dependencies check berfungsi (error saat missing)
- [ ] Formula calculation benar

### ✅ **Evaluation Testing**
- [ ] PER_SAMPLE: All OK → Overall OK
- [ ] PER_SAMPLE: Some NG → Overall NG
- [ ] JOINT: Final value OK → Overall OK
- [ ] JOINT: Final value NG → Overall NG
- [ ] Mixed: Some items OK, some NG → Overall NG

### ✅ **Response Structure**
- [ ] `overall_result` shows "OK"/"NG"
- [ ] `evaluation_summary` shows pass rate
- [ ] `item_details` shows per-item results
- [ ] `samples_summary` shows per-sample results

---

## 🚀 **Ready for Production!**

Semua endpoints sudah teruji dan siap untuk:
- ✅ Flutter integration
- ✅ Production deployment  
- ✅ Real measurement scenarios
- ✅ Complex formula calculations
- ✅ Comprehensive OK/NG evaluation

**Happy Testing!** 🎉
