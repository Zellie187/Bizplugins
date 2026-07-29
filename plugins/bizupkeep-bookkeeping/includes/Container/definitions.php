<?php

declare(strict_types=1);

use BizHub\Bookkeeping\Accounts\AccountRepository;
use BizHub\Bookkeeping\Accounts\AccountService;
use BizHub\Bookkeeping\Accounts\CompanySettingsRepository;
use BizHub\Bookkeeping\BankImport\BankImportService;
use BizHub\Bookkeeping\BankImport\ImportMappingRepository;
use BizHub\Bookkeeping\BankImport\StagedTransactionRepository;
use BizHub\Bookkeeping\Billing\SubscriptionRepository;
use BizHub\Bookkeeping\Billing\SubscriptionService;
use BizHub\Bookkeeping\Contracts\AccountRepositoryInterface;
use BizHub\Bookkeeping\Contracts\AccountServiceInterface;
use BizHub\Bookkeeping\Contracts\BankImportServiceInterface;
use BizHub\Bookkeeping\Contracts\CompanySettingsRepositoryInterface;
use BizHub\Bookkeeping\Contracts\CustomerRepositoryInterface;
use BizHub\Bookkeeping\Contracts\FinancialStatementsServiceInterface;
use BizHub\Bookkeeping\Contracts\ImportMappingRepositoryInterface;
use BizHub\Bookkeeping\Contracts\InvoiceRepositoryInterface;
use BizHub\Bookkeeping\Contracts\InvoiceServiceInterface;
use BizHub\Bookkeeping\Contracts\JournalRepositoryInterface;
use BizHub\Bookkeeping\Contracts\LedgerServiceInterface;
use BizHub\Bookkeeping\Contracts\RecurringOccurrenceRepositoryInterface;
use BizHub\Bookkeeping\Contracts\RecurringTemplateRepositoryInterface;
use BizHub\Bookkeeping\Contracts\RecurringTransactionServiceInterface;
use BizHub\Bookkeeping\Contracts\StagedTransactionRepositoryInterface;
use BizHub\Bookkeeping\Contracts\SubscriptionRepositoryInterface;
use BizHub\Bookkeeping\Contracts\SubscriptionServiceInterface;
use BizHub\Bookkeeping\Contracts\TransactionCaptureServiceInterface;
use BizHub\Bookkeeping\Invoicing\CustomerRepository;
use BizHub\Bookkeeping\Invoicing\InvoiceRepository;
use BizHub\Bookkeeping\Invoicing\InvoiceService;
use BizHub\Bookkeeping\Ledger\JournalRepository;
use BizHub\Bookkeeping\Ledger\LedgerService;
use BizHub\Bookkeeping\Ledger\TransactionCaptureService;
use BizHub\Bookkeeping\Recurring\RecurringOccurrenceRepository;
use BizHub\Bookkeeping\Recurring\RecurringTemplateRepository;
use BizHub\Bookkeeping\Recurring\RecurringTransactionService;
use BizHub\Bookkeeping\Reporting\FinancialStatementsService;

/*
 * Contributed into BizHub's shared container via the
 * 'bizhub/container_definitions' filter - see bizupkeep-bookkeeping.php.
 * Concrete classes (repositories' dependents, exporters, admin page,
 * etc.) do not need entries here: PHP-DI autowires them automatically.
 * Only interface -> concrete bindings need to be declared explicitly.
 *
 * Plain DI\autowire() per interface is sufficient throughout this
 * plugin - unlike bizupkeep-workflow's WorkflowManager, nothing here is
 * looked up simultaneously by its own concrete class AND its interface
 * from different call sites, so no DI\get() aliasing is needed.
 */
return [
    AccountRepositoryInterface::class => DI\autowire(AccountRepository::class),
    AccountServiceInterface::class => DI\autowire(AccountService::class),
    CompanySettingsRepositoryInterface::class => DI\autowire(CompanySettingsRepository::class),

    JournalRepositoryInterface::class => DI\autowire(JournalRepository::class),
    LedgerServiceInterface::class => DI\autowire(LedgerService::class),
    TransactionCaptureServiceInterface::class => DI\autowire(TransactionCaptureService::class),

    FinancialStatementsServiceInterface::class => DI\autowire(FinancialStatementsService::class),

    SubscriptionRepositoryInterface::class => DI\autowire(SubscriptionRepository::class),
    SubscriptionServiceInterface::class => DI\autowire(SubscriptionService::class),

    ImportMappingRepositoryInterface::class => DI\autowire(ImportMappingRepository::class),
    StagedTransactionRepositoryInterface::class => DI\autowire(StagedTransactionRepository::class),
    BankImportServiceInterface::class => DI\autowire(BankImportService::class),

    RecurringTemplateRepositoryInterface::class => DI\autowire(RecurringTemplateRepository::class),
    RecurringOccurrenceRepositoryInterface::class => DI\autowire(RecurringOccurrenceRepository::class),
    RecurringTransactionServiceInterface::class => DI\autowire(RecurringTransactionService::class),

    CustomerRepositoryInterface::class => DI\autowire(CustomerRepository::class),
    InvoiceRepositoryInterface::class => DI\autowire(InvoiceRepository::class),
    InvoiceServiceInterface::class => DI\autowire(InvoiceService::class),
];
