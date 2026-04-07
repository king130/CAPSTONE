<script setup lang="ts">
import {
  FlexRender,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  useVueTable,
} from '@tanstack/vue-table'
import { computed, ref } from 'vue'

import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import Table from '@/components/ui/table/Table.vue'
import TableBody from '@/components/ui/table/TableBody.vue'
import TableCell from '@/components/ui/table/TableCell.vue'
import TableHead from '@/components/ui/table/TableHead.vue'
import TableHeader from '@/components/ui/table/TableHeader.vue'
import TableRow from '@/components/ui/table/TableRow.vue'

const props = withDefaults(
  defineProps<{
    columns: any[]
    data: any[]
    loading?: boolean
    searchPlaceholder?: string
    emptyText?: string
    pageSize?: number
  }>(),
  {
    loading: false,
    searchPlaceholder: 'Search...',
    emptyText: 'No results found.',
    pageSize: 8,
  },
)

const globalFilter = ref('')
const pagination = ref({
  pageIndex: 0,
  pageSize: props.pageSize,
})

const table = useVueTable({
  get data() {
    return props.data
  },
  get columns() {
    return props.columns
  },
  state: {
    get globalFilter() {
      return globalFilter.value
    },
    get pagination() {
      return pagination.value
    },
  },
  onGlobalFilterChange: (updaterOrValue) => {
    globalFilter.value =
      typeof updaterOrValue === 'function' ? updaterOrValue(globalFilter.value) : updaterOrValue
  },
  onPaginationChange: (updaterOrValue) => {
    pagination.value =
      typeof updaterOrValue === 'function' ? updaterOrValue(pagination.value) : updaterOrValue
  },
  getCoreRowModel: getCoreRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  globalFilterFn: 'includesString',
})

const skeletonRows = computed(() => Array.from({ length: props.pageSize }))
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <Input
        :model-value="globalFilter"
        :placeholder="searchPlaceholder"
        class="w-full sm:max-w-sm"
        @update:modelValue="table.setGlobalFilter($event)"
      />
      <p class="text-sm text-muted-foreground">
        {{ table.getFilteredRowModel().rows.length }} result{{ table.getFilteredRowModel().rows.length === 1 ? '' : 's' }}
      </p>
    </div>

    <div class="rounded-xl border border-border bg-white">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id" class="hidden" />
          </TableRow>
          <template v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
            <TableRow>
              <TableHead v-for="header in headerGroup.headers" :key="header.id">
                <FlexRender
                  v-if="!header.isPlaceholder"
                  :render="header.column.columnDef.header"
                  :props="header.getContext()"
                />
              </TableHead>
            </TableRow>
          </template>
        </TableHeader>

        <TableBody>
          <template v-if="loading">
            <TableRow v-for="(_, index) in skeletonRows" :key="index">
              <TableCell v-for="column in columns" :key="String(column.id ?? column.accessorKey ?? index)">
                <Skeleton class="h-5 w-full" />
              </TableCell>
            </TableRow>
          </template>

          <template v-else-if="table.getRowModel().rows.length">
            <TableRow v-for="row in table.getRowModel().rows" :key="row.id">
              <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </TableCell>
            </TableRow>
          </template>

          <TableRow v-else>
            <TableCell :colspan="columns.length" class="py-10 text-center text-muted-foreground">
              {{ emptyText }}
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <div class="flex items-center justify-between">
      <p class="text-sm text-muted-foreground">
        Page {{ table.getState().pagination.pageIndex + 1 }} of {{ table.getPageCount() || 1 }}
      </p>
      <div class="flex gap-2">
        <Button variant="outline" size="sm" :disabled="!table.getCanPreviousPage()" @click="table.previousPage()">
          Previous
        </Button>
        <Button variant="outline" size="sm" :disabled="!table.getCanNextPage()" @click="table.nextPage()">
          Next
        </Button>
      </div>
    </div>
  </div>
</template>
