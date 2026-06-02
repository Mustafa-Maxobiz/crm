# Pricing Type Feature Implementation

## Overview
This document covers two major improvements to the Lead module:
1. **Pricing Type Field**: Distinguish between Fixed Price and Hourly Rate pricing models
2. **Optional Contact Fields**: Make contact person fields optional for leads from freelance platforms

---

## Feature 1: Pricing Type Field

Added a "Pricing Type" field to the Lead module to distinguish between Fixed Price and Hourly Rate pricing models. This resolves the ambiguity in the "Lead Value" field and improves pipeline reporting accuracy.

## Changes Made

### 1. Database Changes

#### Migration: `2026_05_15_131331_add_pricing_type_to_leads_table.php`
- Added `pricing_type` column to `leads` table
- Type: ENUM with values: 'fixed', 'hourly'
- Default: 'fixed'
- Position: After `lead_value` column

#### Migration: `2026_05_15_131422_add_pricing_type_attribute.php`
- Added `pricing_type` attribute to the `attributes` table
- Created two attribute options:
  - Fixed Price (sort_order: 1)
  - Hourly Rate (sort_order: 2)

### 2. Model Changes

#### File: `packages/Webkul/Lead/src/Models/Lead.php`
- Added `pricing_type` to the `$fillable` array

### 3. Repository Changes

#### File: `packages/Webkul/Lead/src/Repositories/LeadRepository.php`
- Added `pricing_type` to the `$fieldSearchable` array

### 4. View Changes

#### File: `packages/Webkul/Admin/src/Resources/views/leads/create.blade.php`
- Excluded `pricing_type` from the first attributes component (NOTIN list)
- Included `pricing_type` in the second attributes component alongside `lead_value`

#### File: `packages/Webkul/Admin/src/Resources/views/leads/edit.blade.php`
- Excluded `pricing_type` from the first attributes component (NOTIN list)
- Included `pricing_type` in the second attributes component alongside `lead_value`

### 5. Seeder Changes

#### File: `packages/Webkul/Installer/src/Database/Seeders/Attribute/AttributeSeeder.php`
- Added `pricing_type` attribute definition
- Added attribute options seeding logic for Fixed Price and Hourly Rate

### 6. Translation Changes

#### File: `packages/Webkul/Installer/src/Resources/lang/en/app.php`
- Added translations:
  - `pricing-type`: "Pricing Type"
  - `fixed-price`: "Fixed Price"
  - `hourly-rate`: "Hourly Rate"

## Features

1. **Pricing Type Dropdown**: Users can now select between "Fixed Price" (default) and "Hourly Rate" when creating or editing leads.

2. **Form Layout**: The pricing type field appears alongside the lead value field in the lead creation/edit forms.

3. **Searchable**: The pricing_type field is searchable in the lead repository.

4. **Database Integrity**: The field has a default value of 'fixed' to ensure backward compatibility.

## Usage

### Creating a New Lead
1. Navigate to Leads → Create Lead
2. Fill in the lead details
3. Select "Pricing Type" from the dropdown (Fixed Price or Hourly Rate)
4. Enter the "Lead Value" amount
5. Complete other required fields and save

### Editing an Existing Lead
1. Navigate to the lead detail page
2. Click Edit
3. Update the "Pricing Type" if needed
4. Modify the "Lead Value" accordingly
5. Save changes

## Migration Commands

To apply these changes to an existing installation:

```bash
# Run migrations
php artisan migrate

# The migrations will:
# 1. Add pricing_type column to leads table
# 2. Add pricing_type attribute and options
```

## Rollback

To rollback these changes:

```bash
php artisan migrate:rollback --step=2
```

This will:
1. Remove the pricing_type attribute and options
2. Remove the pricing_type column from leads table

## Future Enhancements

Potential improvements for this feature:

1. **Hourly Rate Calculator**: Add fields for hours and rate when "Hourly Rate" is selected
2. **Pipeline Reports**: Update pipeline value calculations to differentiate between pricing types
3. **Export/Import**: Include pricing_type in data import/export functionality
4. **API Support**: Ensure pricing_type is included in API responses
5. **Validation Rules**: Add custom validation based on pricing type selection

## Notes

- Existing leads will have `pricing_type` set to 'fixed' by default
- The field is required (cannot be null)
- The attribute is marked as `quick_add` enabled for quick lead creation
- The field appears in the same section as lead_value, lead_type_id, and lead_source_id


---

## Feature 2: Optional Contact Person Fields

### Overview
Made contact person fields (Name, Email, Contact Numbers) optional during lead creation to support leads from freelance platforms where client contact details are not available initially.

### Changes Made
- Updated `is_required` attribute for person name, emails, and contact_numbers to `0`
- Modified AttributeSeeder for fresh installations
- Created migration: `2026_05_15_155536_make_contact_person_fields_optional.php`

### Benefits
- ✅ Support for Upwork, Contra, Dribbble leads without contact info
- ✅ No more dummy data ("Unknown", "N/A") in the database
- ✅ Faster lead creation workflow
- ✅ Better data quality and accuracy

### Usage
Users can now:
1. Create leads without entering contact person details
2. Add contact information later when client shares it
3. Still create leads with full contact info for traditional sources

For detailed documentation, see: [CONTACT_FIELDS_OPTIONAL.md](./CONTACT_FIELDS_OPTIONAL.md)

---

## Summary of All Changes

### Migrations Created
1. `2026_05_15_131331_add_pricing_type_to_leads_table.php` - Added pricing_type column (later removed)
2. `2026_05_15_131422_add_pricing_type_attribute.php` - Added pricing_type attribute and options
3. `2026_05_15_154952_remove_pricing_type_column_from_leads_table.php` - Removed pricing_type column
4. `2026_05_15_155536_make_contact_person_fields_optional.php` - Made contact fields optional

### Files Modified
1. Lead Model (`packages/Webkul/Lead/src/Models/Lead.php`)
2. Lead Repository (`packages/Webkul/Lead/src/Repositories/LeadRepository.php`)
3. Lead Create View (`packages/Webkul/Admin/src/Resources/views/leads/create.blade.php`)
4. Lead Edit View (`packages/Webkul/Admin/src/Resources/views/leads/edit.blade.php`)
5. Attribute Seeder (`packages/Webkul/Installer/src/Database/Seeders/Attribute/AttributeSeeder.php`)
6. Installer Language File (`packages/Webkul/Installer/src/Resources/lang/en/app.php`)

### Testing Completed
- [x] Pricing Type field displays correctly
- [x] Pricing Type saves and retrieves properly
- [x] Expected Close Date works correctly
- [x] Contact fields are now optional
- [x] Leads can be created without contact information
- [x] Contact information can be added later

## Quick Start

### Apply All Changes
```bash
# Run all migrations
php artisan migrate

# Clear all caches
php artisan optimize:clear
```

### Rollback All Changes
```bash
# Rollback all 4 migrations
php artisan migrate:rollback --step=4
```

## Support

For issues or questions:
1. Check the detailed documentation files
2. Review the migration files
3. Verify database changes were applied correctly
4. Clear all caches if changes don't appear
