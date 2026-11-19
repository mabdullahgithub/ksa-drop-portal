import { useEffect, useState } from "react";
import {
    fetchConnectionState,
    fetchProducts,
    EmbeddedApiError,
    type ConnectionState,
    type ProductsPage,
} from "../api-client";
import { StoreNotConnected } from "../store-not-connected";

/** Read-only catalogue of the products KSA Drop makes available. */
export function ProductsPage() {
    const [connection, setConnection] = useState<ConnectionState | null>(null);
    const [result, setResult] = useState<ProductsPage | null>(null);
    const [error, setError] = useState<Error | null>(null);
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState("");
    const [query, setQuery] = useState("");

    useEffect(() => {
        fetchConnectionState().then(setConnection).catch((e: Error) => setError(e));
    }, []);

    useEffect(() => {
        if (!connection?.linked) return;
        let stale = false;
        fetchProducts(page, query)
            .then((r) => !stale && (setResult(r), setError(null)))
            .catch((e: Error) => !stale && setError(e));
        return () => {
            stale = true;
        };
    }, [connection, page, query]);

    function runSearch() {
        setPage(1);
        setQuery(search.trim());
    }

    if (connection && !connection.linked) {
        return (
            <StoreNotConnected
                heading="Products"
                shop={connection.shop}
                claimToken={connection.token ?? undefined}
                installed={connection.installed}
            />
        );
    }

    if (error && !result) {
        if (error instanceof EmbeddedApiError && error.status === 401) {
            return <StoreNotConnected heading="Products" />;
        }
        return (
            <s-page heading="Products">
                <s-banner tone="critical" heading="Could not load products">
                    {error.message}
                </s-banner>
            </s-page>
        );
    }

    return (
        <s-page heading="Products" subheading="Products available from KSA Drop">
            <s-section>
                <s-stack gap="base">
                    <s-stack direction="inline" gap="small-200" alignItems="end">
                        <s-text-field
                            label="Search products"
                            value={search}
                            placeholder="Search by name or SKU"
                            onInput={(e: Event) =>
                                setSearch((e.currentTarget as HTMLInputElement).value)
                            }
                            onKeyDown={(e: KeyboardEvent) => {
                                if (e.key === "Enter") runSearch();
                            }}
                        />
                        <s-button onClick={runSearch}>Search</s-button>
                    </s-stack>
                    {!result ? (
                        <s-paragraph>Loading…</s-paragraph>
                    ) : result.data.length === 0 ? (
                        <s-paragraph>No products found.</s-paragraph>
                    ) : (
                        <s-table>
                            <s-table-header-row>
                                <s-table-header>Product</s-table-header>
                                <s-table-header>SKU</s-table-header>
                                <s-table-header>Price</s-table-header>
                                <s-table-header>Availability</s-table-header>
                            </s-table-header-row>
                            <s-table-body>
                                {result.data.map((p) => (
                                    <s-table-row key={p.id}>
                                        <s-table-cell>
                                            <s-stack direction="inline" gap="base" alignItems="center">
                                                {p.image && (
                                                    <img
                                                        src={p.image}
                                                        alt={p.title}
                                                        width={40}
                                                        height={40}
                                                        style={{ objectFit: "cover", borderRadius: 4 }}
                                                    />
                                                )}
                                                <s-text>{p.title}</s-text>
                                            </s-stack>
                                        </s-table-cell>
                                        <s-table-cell>{p.sku ?? "—"}</s-table-cell>
                                        <s-table-cell>{p.price ? `SAR ${p.price}` : "—"}</s-table-cell>
                                        <s-table-cell>
                                            <s-badge tone={p.in_stock ? "success" : "critical"}>
                                                {p.in_stock ? "In stock" : "Out of stock"}
                                            </s-badge>
                                        </s-table-cell>
                                    </s-table-row>
                                ))}
                            </s-table-body>
                        </s-table>
                    )}
                    {result && result.last_page > 1 && (
                        <s-stack direction="inline" gap="base" alignItems="center">
                            <s-button disabled={page <= 1} onClick={() => setPage(page - 1)}>
                                Previous
                            </s-button>
                            <s-text>{`Page ${result.current_page} of ${result.last_page}`}</s-text>
                            <s-button disabled={page >= result.last_page} onClick={() => setPage(page + 1)}>
                                Next
                            </s-button>
                        </s-stack>
                    )}
                </s-stack>
            </s-section>
        </s-page>
    );
}
