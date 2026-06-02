# Lead Pipeline Sorting Feature

## Overview
The lead pipeline now includes a comprehensive sorting system that allows users to organize leads within each pipeline stage according to different criteria.

---

## 🎯 Features Implemented

### **Default Sorting**
- **Newest First** (default): Leads are sorted by creation date with the most recent leads at the top
- This ensures new opportunities are immediately visible

### **Available Sort Options**

#### 1. **Sort by Date**
- **Newest First** ⬇️
  - Most recent leads appear at the top
  - Default sorting option
  - Best for: Prioritizing new opportunities

- **Oldest First** ⬆️
  - Oldest leads appear at the top
  - Best for: Following up on neglected leads

#### 2. **Sort by Lead Value**
- **Value: High to Low** ⬇️
  - Highest value leads appear first
  - Best for: Focusing on high-value opportunities

- **Value: Low to High** ⬆️
  - Lowest value leads appear first
  - Best for: Quick wins with smaller deals

#### 3. **Sort by Title**
- **Title: A-Z** ⬆️
  - Alphabetical order
  - Best for: Finding specific leads by name

- **Title: Z-A** ⬇️
  - Reverse alphabetical order
  - Best for: Alternative organization

---

## 📍 Where to Find

### Location:
**Leads Page → Kanban View → Toolbar**

The sort dropdown is located in the toolbar, between the **Filter** button and the **View Switcher**.

### Visual Indicator:
```
┌─────────────────────────────────────────────────┐
│ [Search] [Filter] [Sort ▼] [View Switcher]     │
└─────────────────────────────────────────────────┘
```

---

## 🎨 How to Use

### Step 1: Open Leads Page
Navigate to: `http://your-domain/admin/leads`

### Step 2: Click Sort Dropdown
Click the **Sort** button in the toolbar (shows current sort option)

### Step 3: Select Sort Option
Choose from the available options:
- Newest First (default)
- Oldest First
- Value: High to Low
- Value: Low to High
- Title: A-Z
- Title: Z-A

### Step 4: View Results
Leads in all pipeline stages will be re-sorted according to your selection

---

## 💡 Sort Dropdown Interface

```
┌──────────────────────────────────────────┐
│ Sort                                     │
├──────────────────────────────────────────┤
│ ⬇️ Newest First                         │
│    Most recent leads at the top          │
│                                          │
│ ⬆️ Oldest First                         │
│    Oldest leads at the top               │
│                                          │
│ ⬇️ Value: High to Low                   │
│    Highest value leads first             │
│                                          │
│ ⬆️ Value: Low to High                   │
│    Lowest value leads first              │
│                                          │
│ ⬆️ Title: A-Z                           │
│    Alphabetical order                    │
│                                          │
│ ⬇️ Title: Z-A                           │
│    Reverse alphabetical order            │
└──────────────────────────────────────────┘
```

**Active Option:** Highlighted with gray background

---

## 🔄 Behavior

### Persistence
- **Sort preference is saved** in browser local storage
- When you return to the leads page, your last selected sort option is automatically applied
- Each pipeline can have its own sort preference

### Applies to All Stages
- Sorting applies to **all pipeline stages** simultaneously
- Each stage column shows leads sorted by the selected criteria
- Maintains consistency across the entire pipeline view

### Works with Filters
- Sorting works **in combination with filters**
- First apply filters, then sort the filtered results
- Or sort first, then filter the sorted results

### Pagination
- Sorting is maintained when loading more leads (scroll pagination)
- New leads loaded via scroll maintain the selected sort order

---

## 🎯 Use Cases

### 1. **Daily Lead Review**
**Sort:** Newest First (default)
- Review new leads that came in today
- Quickly identify fresh opportunities
- Ensure no new lead is missed

### 2. **Follow-up on Old Leads**
**Sort:** Oldest First
- Find leads that have been sitting too long
- Prioritize neglected opportunities
- Clean up stale leads

### 3. **Focus on High-Value Deals**
**Sort:** Value: High to Low
- Prioritize leads with highest potential revenue
- Focus sales efforts on big opportunities
- Maximize revenue potential

### 4. **Quick Wins Strategy**
**Sort:** Value: Low to High
- Close smaller deals quickly
- Build momentum with easy wins
- Improve conversion metrics

### 5. **Find Specific Lead**
**Sort:** Title: A-Z
- Quickly locate a lead by name
- Organize leads alphabetically
- Better visual scanning

---

## 🔧 Technical Details

### Backend Implementation
**File:** `packages/Webkul/Admin/src/Http/Controllers/Lead/LeadController.php`

**Changes:**
- Added `sort_by` and `sort_order` parameters to `get()` method
- Default: `sort_by=created_at`, `sort_order=desc`
- Applies `orderBy()` to query before pagination

**Supported Sort Fields:**
- `created_at` - Lead creation date
- `lead_value` - Lead monetary value
- `title` - Lead title/name

### Frontend Implementation
**Files:**
- `packages/Webkul/Admin/src/Resources/views/leads/index/kanban/sort.blade.php` - Sort dropdown component
- `packages/Webkul/Admin/src/Resources/views/leads/index/kanban/toolbar.blade.php` - Toolbar integration
- `packages/Webkul/Admin/src/Resources/views/leads/index/kanban.blade.php` - Vue component logic

**Vue Component Changes:**
- Added `applied.sort` state with `by` and `order` properties
- Added `sortLabel` computed property for dropdown display
- Added `sort()` method to handle sort changes
- Updated `get()` method to include sort parameters
- Updated `boot()` method to restore sort from local storage

### Translations
**File:** `packages/Webkul/Admin/src/Resources/lang/en/app.php`

**Added Keys:**
- `admin::app.leads.index.kanban.toolbar.sort.newest-first`
- `admin::app.leads.index.kanban.toolbar.sort.oldest-first`
- `admin::app.leads.index.kanban.toolbar.sort.value-high-low`
- `admin::app.leads.index.kanban.toolbar.sort.value-low-high`
- `admin::app.leads.index.kanban.toolbar.sort.title-az`
- `admin::app.leads.index.kanban.toolbar.sort.title-za`

---

## 📊 Sorting Logic

### Database Query
```php
// Default
$query->orderBy('created_at', 'desc');

// High to Low Value
$query->orderBy('lead_value', 'desc');

// Alphabetical
$query->orderBy('title', 'asc');
```

### API Request
```
GET /admin/leads/get?sort_by=created_at&sort_order=desc
```

### Local Storage
```json
{
  "applied": {
    "sort": {
      "by": "created_at",
      "order": "desc"
    }
  }
}
```

---

## 🧪 Testing

### Test Scenarios

1. **Default Sort**
   - Open leads page
   - Verify newest leads appear first
   - Check sort dropdown shows "Newest First"

2. **Change Sort**
   - Click sort dropdown
   - Select "Oldest First"
   - Verify leads reorder with oldest first

3. **Sort by Value**
   - Select "Value: High to Low"
   - Verify highest value leads appear first
   - Check all stages are sorted

4. **Persistence**
   - Select a sort option
   - Refresh the page
   - Verify sort option is maintained

5. **With Filters**
   - Apply a filter (e.g., by sales person)
   - Change sort option
   - Verify filtered results are sorted correctly

6. **Pagination**
   - Select a sort option
   - Scroll to load more leads
   - Verify new leads maintain sort order

---

## 🎨 UI/UX Features

### Visual Feedback
- **Active option** highlighted with gray background
- **Current sort** displayed in dropdown button
- **Icons** show sort direction (⬆️ up, ⬇️ down)
- **Descriptions** explain each sort option

### Responsive Design
- Works on desktop and mobile
- Dropdown adapts to screen size
- Touch-friendly on mobile devices

### Accessibility
- Keyboard navigation supported
- Screen reader friendly
- Clear labels and descriptions

---

## 🚀 Benefits

### For Sales Teams
✅ **Prioritize effectively** - Focus on what matters most
✅ **Find leads quickly** - Multiple organization methods
✅ **Improve follow-up** - Identify neglected leads
✅ **Maximize revenue** - Focus on high-value opportunities

### For Managers
✅ **Better visibility** - Understand lead distribution
✅ **Track performance** - Monitor lead age and value
✅ **Optimize workflow** - Assign leads strategically
✅ **Data-driven decisions** - Sort by relevant metrics

### For Users
✅ **Flexible organization** - Choose what works for you
✅ **Persistent preferences** - Saves your choice
✅ **Easy to use** - Simple dropdown interface
✅ **Fast performance** - Instant re-sorting

---

## 📝 Notes

- Sort preference is **per-browser** (uses local storage)
- Sorting does **not affect** drag-and-drop functionality
- Moving a lead between stages **maintains** the current sort
- Sort applies to **kanban view only** (table view has its own sorting)

---

## 🔮 Future Enhancements (Optional)

### Additional Sort Options
- Sort by expected close date
- Sort by last activity date
- Sort by follow-up date
- Sort by contact person name
- Sort by source or type

### Advanced Features
- Multi-level sorting (primary + secondary sort)
- Custom sort orders
- Save multiple sort presets
- Sort by custom fields
- Per-stage sorting (different sort for each stage)

### UI Improvements
- Quick sort buttons (one-click common sorts)
- Sort direction toggle button
- Visual indicators on lead cards
- Sort history/recent sorts

---

**Last Updated**: May 15, 2026
**Version**: 1.0
**Status**: ✅ Complete and Ready to Use
