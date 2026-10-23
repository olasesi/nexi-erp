import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Download, RefreshCw } from "lucide-react";

import {
  getBalanceSheet,
  getCashFlow,
  getProfitAndLoss,
  getSalesByCategory,
  getSalesByProduct,
  getVatSummary,
} from "@/lib/api";
import {
  downloadCsv,
  formatCurrency,
  formatNumber,
  formatPercent,
  toCsv,
  type CsvColumn,
} from "@/lib/format";
import { useCompany } from "@/lib/use-company";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { PageHeader } from "@/components/ui/page-header";
import { ErrorState, LoadingState } from "@/components/ui/states";
import { Stat } from "@/components/ui/stat";
import type {
  AccountBalance,
  BalanceSheetResponse,
  BalanceSheetSide,
  CashFlowResponse,
  ProfitAndLossResponse,
  SalesByCategoryResponse,
  SalesByProductResponse,
  VatSummaryResponse,
} from "@/types";

interface ReportQuery {
  company_id: number;
  from: string;
  to: string;
}

type ReportData =
  | ProfitAndLossResponse
  | BalanceSheetResponse
  | CashFlowResponse
  | VatSummaryResponse
  | SalesByProductResponse
  | SalesByCategoryResponse;

type ReportTab =
  "profit-and-loss" | "balance-sheet" | "cash-flow" | "vat" | "products" | "categories";

const TABS: Array<{ id: ReportTab; label: string; description: string }> = [
  {
    id: "profit-and-loss",
    label: "Profit & loss",
    description: "Revenue and expenses booked in the selected window.",
  },
  {
    id: "balance-sheet",
    label: "Balance sheet",
    description: "Assets, liabilities and equity as of the selected date.",
  },
  {
    id: "cash-flow",
    label: "Cash flow",
    description: "Money in and out of the business for the window.",
  },
  { id: "vat", label: "VAT", description: "Net, tax and gross totals grouped by tax rate." },
  {
    id: "products",
    label: "Sales by product",
    description: "Revenue and quantity sold per product.",
  },
  {
    id: "categories",
    label: "Sales by category",
    description: "Revenue and quantity sold per product category.",
  },
];

function isoDate(date: Date): string {
  return date.toISOString().slice(0, 10);
}

function defaultRange(): { from: string; to: string } {
  const today = new Date();
  return { from: isoDate(new Date(today.getFullYear(), 0, 1)), to: isoDate(today) };
}

export function ReportsPage() {
  const [tab, setTab] = useState<ReportTab>("profit-and-loss");
  const [range, setRange] = useState(defaultRange);
  const { companyId, isLoading, error, refetch } = useCompany();

  const query: ReportQuery = useMemo(
    () => ({ company_id: companyId as number, from: range.from, to: range.to }),
    [companyId, range],
  );

  const activeTab = TABS.find((item) => item.id === tab) ?? TABS[0];
  const enabled = companyId !== null && range.from !== "" && range.to !== "";

  const content = useQuery<ReportData>({
    queryKey: ["reports", tab, query],
    queryFn: () => {
      switch (tab) {
        case "balance-sheet":
          return getBalanceSheet({ company_id: query.company_id, as_of: range.to });
        case "cash-flow":
          return getCashFlow(query);
        case "vat":
          return getVatSummary(query);
        case "products":
          return getSalesByProduct(query);
        case "categories":
          return getSalesByCategory(query);
        case "profit-and-loss":
        default:
          return getProfitAndLoss(query);
      }
    },
    enabled,
  });

  if (isLoading) {
    return <LoadingState label="Resolving your companyâ€¦" />;
  }

  if (error || companyId === null) {
    return (
      <ErrorState
        error={
          error instanceof Error ? error : new Error("No company is linked to this account yet.")
        }
        onRetry={() => void refetch()}
      />
    );
  }

  return (
    <div className="page">
      <PageHeader
        title="Reports"
        description={activeTab.description}
        actions={
          <>
            <label className="select-group">
              From
              <input
                type="date"
                className="select"
                value={range.from}
                max={range.to}
                onChange={(event) =>
                  setRange((current) => ({ ...current, from: event.target.value }))
                }
              />
            </label>
            <label className="select-group">
              {tab === "balance-sheet" ? "As of" : "To"}
              <input
                type="date"
                className="select"
                value={range.to}
                min={tab === "balance-sheet" ? undefined : range.from}
                onChange={(event) =>
                  setRange((current) => ({ ...current, to: event.target.value }))
                }
              />
            </label>
            <Button variant="secondary" onClick={() => void content.refetch()}>
              <RefreshCw className="icon icon--sm" /> Refresh
            </Button>
          </>
        }
      />

      <div className="tabs tabs--block" role="tablist" aria-label="Report types">
        {TABS.map((item) => (
          <button
            key={item.id}
            type="button"
            role="tab"
            aria-selected={tab === item.id}
            aria-current={tab === item.id ? "page" : undefined}
            onClick={() => setTab(item.id)}
            className={`tabs__tab ${tab === item.id ? "tabs__tab--active" : ""}`}
          >
            {item.label}
          </button>
        ))}
      </div>

      {content.isError ? (
        <ErrorState error={content.error} onRetry={() => void content.refetch()} />
      ) : content.isLoading || !content.data ? (
        <div className="grid-fixed grid-fixed--4">
          {Array.from({ length: 4 }).map((_, index) => (
            <div key={index} className="skeleton skeleton--stat" />
          ))}
        </div>
      ) : (
        <ReportContent tab={tab} from={range.from} to={range.to} data={content.data} />
      )}
    </div>
  );
}

function ReportContent({
  tab,
  from,
  to,
  data,
}: {
  tab: ReportTab;
  from: string;
  to: string;
  data: ReportData;
}) {
  switch (tab) {
    case "balance-sheet":
      return <BalanceSheetReport data={data as BalanceSheetResponse} />;
    case "cash-flow":
      return <CashFlowReport data={data as CashFlowResponse} />;
    case "vat":
      return <VatReport data={data as VatSummaryResponse} />;
    case "products":
      return <SalesProductsReport data={data as SalesByProductResponse} from={from} to={to} />;
    case "categories":
      return <SalesCategoriesReport data={data as SalesByCategoryResponse} from={from} to={to} />;
    case "profit-and-loss":
    default:
      return <ProfitAndLossReport data={data as ProfitAndLossResponse} from={from} to={to} />;
  }
}

function ExportCsvButton({
  filename,
  rows,
  columns,
}: {
  filename: string;
  rows: ReadonlyArray<Record<string, unknown>>;
  columns: CsvColumn[];
}) {
  return (
    <Button
      variant="ghost"
      size="sm"
      onClick={() => downloadCsv(filename, toCsv(rows, columns))}
      aria-label={`Export ${filename}`}
    >
      <Download className="icon icon--xs" /> CSV
    </Button>
  );
}

function AccountTable({ rows, total }: { rows: AccountBalance[]; total: number }) {
  if (rows.length === 0) {
    return <p className="state__empty">Nothing booked in this period.</p>;
  }

  return (
    <div className="inline-table__scroll">
      <table className="inline-table">
        <thead>
          <tr className="inline-table__head-row">
            <th className="inline-table__th">Code</th>
            <th className="inline-table__th">Account</th>
            <th className="inline-table__th inline-table__th--num">Balance</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={`${row.code}-${row.name}`} className="inline-table__body-row">
              <td className="inline-table__td">{row.code}</td>
              <td className="inline-table__td">{row.name}</td>
              <td className="inline-table__td inline-table__td--num">
                {formatCurrency(row.balance)}
              </td>
            </tr>
          ))}
          <tr className="inline-table__body-row">
            <td className="inline-table__td" />
            <td className="inline-table__td inline-table__td--strong">Total</td>
            <td className="inline-table__td inline-table__td--num inline-table__td--strong">
              {formatCurrency(total)}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  );
}

function ProfitAndLossReport({
  data,
  from,
  to,
}: {
  data: ProfitAndLossResponse;
  from: string;
  to: string;
}) {
  const exportRows = [
    ...data.revenue.map((row) => ({ section: "revenue", ...row })),
    ...data.expenses.map((row) => ({ section: "expense", ...row })),
  ];

  return (
    <div className="stack stack--4">
      <div className="grid-fixed grid-fixed--3">
        <Stat label="Revenue" value={data.total_revenue} currency />
        <Stat label="Expenses" value={data.total_expenses} currency />
        <Stat
          label="Net income"
          value={data.net_income}
          currency
          tone={data.net_income >= 0 ? "success" : "danger"}
        />
      </div>

      <div className="grid-fixed grid-fixed--2">
        <Card
          title="Revenue"
          description="Income accounts in the period"
          action={
            <ExportCsvButton
              filename={`profit-and-loss-${from}-${to}.csv`}
              rows={exportRows}
              columns={[
                { key: "section", label: "Section" },
                { key: "code", label: "Code" },
                { key: "name", label: "Account" },
                { key: "balance", label: "Balance" },
              ]}
            />
          }
        >
          <AccountTable rows={data.revenue} total={data.total_revenue} />
        </Card>
        <Card title="Expenses" description="Expense accounts in the period">
          <AccountTable rows={data.expenses} total={data.total_expenses} />
        </Card>
      </div>
    </div>
  );
}

function BalanceSheetReport({ data }: { data: BalanceSheetResponse }) {
  return (
    <div className="stack stack--4">
      <div className="grid-fixed grid-fixed--3">
        <Stat label="Total assets" value={data.total_assets} currency />
        <Stat label="Total liabilities" value={data.total_liabilities} currency />
        <Stat label="Total equity" value={data.total_equity} currency />
      </div>

      <div className="grid-fixed grid-fixed--2">
        <Card title="Assets" description={`As of ${data.as_of}`}>
          <BalanceTable rows={data.assets} total={data.total_assets} />
        </Card>
        <Card title="Liabilities & equity" description={`As of ${data.as_of}`}>
          <BalanceTable
            rows={[...data.liabilities, ...data.equity]}
            total={data.total_liabilities_and_equity}
          />
        </Card>
      </div>
    </div>
  );
}

function BalanceTable({ rows, total }: { rows: BalanceSheetSide[]; total: number }) {
  if (rows.length === 0) {
    return <p className="state__empty">No balances posted.</p>;
  }

  return (
    <div className="inline-table__scroll">
      <table className="inline-table">
        <thead>
          <tr className="inline-table__head-row">
            <th className="inline-table__th">Account</th>
            <th className="inline-table__th">Type</th>
            <th className="inline-table__th inline-table__th--num">Debit</th>
            <th className="inline-table__th inline-table__th--num">Credit</th>
            <th className="inline-table__th inline-table__th--num">Balance</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={`${row.account_id}-${row.code}`} className="inline-table__body-row">
              <td className="inline-table__td inline-table__td--strong">{row.name}</td>
              <td className="inline-table__td">{row.type}</td>
              <td className="inline-table__td inline-table__td--num">
                {formatCurrency(row.debit)}
              </td>
              <td className="inline-table__td inline-table__td--num">
                {formatCurrency(row.credit)}
              </td>
              <td className="inline-table__td inline-table__td--num inline-table__td--strong">
                {formatCurrency(row.balance)}
              </td>
            </tr>
          ))}
          <tr className="inline-table__body-row">
            <td className="inline-table__td inline-table__td--strong" colSpan={4}>
              Total
            </td>
            <td className="inline-table__td inline-table__td--num inline-table__td--strong">
              {formatCurrency(total)}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  );
}

function CashFlowReport({ data }: { data: CashFlowResponse }) {
  return (
    <div className="grid-fixed grid-fixed--3">
      <Stat label="Inflow" value={data.inflow} currency tone="success" />
      <Stat label="Outflow" value={data.outflow} currency tone="danger" />
      <Stat
        label="Net cash flow"
        value={data.net_cash_flow}
        currency
        tone={data.net_cash_flow >= 0 ? "success" : "danger"}
        hint={`${data.from} â†’ ${data.to}`}
      />
    </div>
  );
}

function VatReport({ data }: { data: VatSummaryResponse }) {
  return (
    <div className="stack stack--4">
      <div className="grid-fixed grid-fixed--3">
        <Stat label="Net" value={data.total_net} currency />
        <Stat label="Tax" value={data.total_tax} currency />
        <Stat label="Gross" value={data.total_gross} currency />
      </div>

      <Card
        title="By tax rate"
        description={`${data.base_currency} Â· ${data.from} â†’ ${data.to}`}
      >
        {data.rates.length === 0 ? (
          <p className="state__empty">No taxed documents in this period.</p>
        ) : (
          <div className="inline-table__scroll">
            <table className="inline-table">
              <thead>
                <tr className="inline-table__head-row">
                  <th className="inline-table__th">Rate</th>
                  <th className="inline-table__th inline-table__th--num">Documents</th>
                  <th className="inline-table__th inline-table__th--num">Net</th>
                  <th className="inline-table__th inline-table__th--num">Tax</th>
                  <th className="inline-table__th inline-table__th--num">Gross</th>
                </tr>
              </thead>
              <tbody>
                {data.rates.map((row) => (
                  <tr key={row.tax_rate} className="inline-table__body-row">
                    <td className="inline-table__td inline-table__td--strong">
                      {formatPercent(row.tax_rate)}
                    </td>
                    <td className="inline-table__td inline-table__td--num">
                      {formatNumber(row.documents)}
                    </td>
                    <td className="inline-table__td inline-table__td--num">
                      {formatCurrency(row.net_amount)}
                    </td>
                    <td className="inline-table__td inline-table__td--num">
                      {formatCurrency(row.tax_amount)}
                    </td>
                    <td className="inline-table__td inline-table__td--num">
                      {formatCurrency(row.gross_amount)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  );
}

function SalesProductsReport({
  data,
  from,
  to,
}: {
  data: SalesByProductResponse;
  from: string;
  to: string;
}) {
  return (
    <Card
      title="Sales by product"
      description={`${data.base_currency} Â· ${formatNumber(data.total_quantity)} units Â· ${formatCurrency(data.total_revenue)}`}
      flush
      action={
        <ExportCsvButton
          filename={`sales-by-product-${from}-${to}.csv`}
          rows={data.items}
          columns={[
            { key: "product_name", label: "Product" },
            { key: "quantity", label: "Quantity" },
            { key: "subtotal", label: "Subtotal" },
            { key: "tax", label: "Tax" },
            { key: "total", label: "Total" },
          ]}
        />
      }
    >
      {data.items.length === 0 ? (
        <p className="state__empty">No product sales in this period.</p>
      ) : (
        <div className="inline-table__scroll">
          <table className="inline-table">
            <thead>
              <tr className="inline-table__head-row">
                <th className="inline-table__th">Product</th>
                <th className="inline-table__th inline-table__th--num">Qty</th>
                <th className="inline-table__th inline-table__th--num">Subtotal</th>
                <th className="inline-table__th inline-table__th--num">Tax</th>
                <th className="inline-table__th inline-table__th--num">Total</th>
              </tr>
            </thead>
            <tbody>
              {data.items.map((row) => (
                <tr key={row.product_id} className="inline-table__body-row">
                  <td className="inline-table__td inline-table__td--strong">{row.product_name}</td>
                  <td className="inline-table__td inline-table__td--num">
                    {formatNumber(row.quantity)}
                  </td>
                  <td className="inline-table__td inline-table__td--num">
                    {formatCurrency(row.subtotal)}
                  </td>
                  <td className="inline-table__td inline-table__td--num">
                    {formatCurrency(row.tax)}
                  </td>
                  <td className="inline-table__td inline-table__td--num inline-table__td--strong">
                    {formatCurrency(row.total)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}

function SalesCategoriesReport({
  data,
  from,
  to,
}: {
  data: SalesByCategoryResponse;
  from: string;
  to: string;
}) {
  return (
    <Card
      title="Sales by category"
      description={`${data.base_currency} Â· ${formatNumber(data.total_quantity)} units Â· ${formatCurrency(data.total_revenue)}`}
      flush
      action={
        <ExportCsvButton
          filename={`sales-by-category-${from}-${to}.csv`}
          rows={data.categories}
          columns={[
            { key: "category_name", label: "Category" },
            { key: "quantity", label: "Quantity" },
            { key: "subtotal", label: "Subtotal" },
            { key: "tax", label: "Tax" },
            { key: "total", label: "Total" },
          ]}
        />
      }
    >
      {data.categories.length === 0 ? (
        <p className="state__empty">No category sales in this period.</p>
      ) : (
        <div className="inline-table__scroll">
          <table className="inline-table">
            <thead>
              <tr className="inline-table__head-row">
                <th className="inline-table__th">Category</th>
                <th className="inline-table__th inline-table__th--num">Qty</th>
                <th className="inline-table__th inline-table__th--num">Subtotal</th>
                <th className="inline-table__th inline-table__th--num">Tax</th>
                <th className="inline-table__th inline-table__th--num">Total</th>
              </tr>
            </thead>
            <tbody>
              {data.categories.map((row) => (
                <tr key={row.category_id} className="inline-table__body-row">
                  <td className="inline-table__td inline-table__td--strong">{row.category_name}</td>
                  <td className="inline-table__td inline-table__td--num">
                    {formatNumber(row.quantity)}
                  </td>
                  <td className="inline-table__td inline-table__td--num">
                    {formatCurrency(row.subtotal)}
                  </td>
                  <td className="inline-table__td inline-table__td--num">
                    {formatCurrency(row.tax)}
                  </td>
                  <td className="inline-table__td inline-table__td--num inline-table__td--strong">
                    {formatCurrency(row.total)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}
