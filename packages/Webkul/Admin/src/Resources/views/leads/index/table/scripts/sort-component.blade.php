        app.component('v-leads-table-sort', {
            template: '#v-leads-table-sort-template',

            computed: {
                datagrid() {
                    let parent = this.$parent;

                    while (parent) {
                        if (parent.applied && typeof parent.get === 'function') {
                            return parent;
                        }

                        parent = parent.$parent;
                    }

                    return null;
                },

                applied() {
                    return this.datagrid?.applied || { sort: { column: null, order: null } };
                },

                sortLabel() {
                    const column = this.applied.sort?.column;
                    const order = this.applied.sort?.order;

                    const labels = {
                        'created_at_desc': '@lang('admin::app.leads.index.kanban.toolbar.sort.newest-first')',
                        'created_at_asc': '@lang('admin::app.leads.index.kanban.toolbar.sort.oldest-first')',
                        'title_asc': '@lang('admin::app.leads.index.kanban.toolbar.sort.title-az')',
                        'title_desc': '@lang('admin::app.leads.index.kanban.toolbar.sort.title-za')',
                    };

                    return labels[`${column}_${order}`] || '@lang('admin::app.leads.index.kanban.toolbar.sort.newest-first')';
                },
            },

            methods: {
                sort(column, order) {
                    if (! this.datagrid) {
                        return;
                    }

                    this.datagrid.applied.sort = {
                        column,
                        order,
                    };

                    this.datagrid.applied.pagination.page = 1;
                    this.datagrid.get();
                },
            },
        });
    </script>
