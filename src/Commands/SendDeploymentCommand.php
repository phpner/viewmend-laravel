<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Commands;

use Illuminate\Console\Command;
use ViewMend\Exception\ViewMendException;
use ViewMend\Laravel\Contracts\ViewMendManagerContract;
use ViewMend\Laravel\Exception\ConfigurationException;
use ViewMend\SiteTracker\PendingEvent;

final class SendDeploymentCommand extends Command
{
    protected $signature = 'viewmend:deployment
        {--event-id= : Required stable deployment event ID}
        {--title=Deployment completed : Deployment title}
        {--site= : Affected site URL}
        {--page=* : Affected page URL; repeat for multiple pages}
        {--environment= : Deployment environment}
        {--description= : Deployment description}
        {--reference= : Reference URL}
        {--connection= : Named ViewMend connection}';

    protected $description = 'Send a deployment event to ViewMend Site Tracker';

    public function handle(ViewMendManagerContract $viewMend): int
    {
        $eventId = $this->requiredOption('event-id', 'The --event-id option is required.');
        if ($eventId === null) {
            return self::INVALID;
        }

        $title = $this->requiredOption('title', 'The --title option must not be empty.');
        if ($title === null) {
            return self::INVALID;
        }

        try {
            $connection = $this->optionalStringOption('connection');
            $pending = $viewMend
                ->connection($connection)
                ->siteTracker()
                ->events()
                ->deployment($eventId, $title);

            $pending = $this->applyStringOption($pending, 'site');
            foreach ($this->pageOptions() as $page) {
                $pending = $pending->page($page);
            }
            $pending = $this->applyStringOption($pending, 'environment');
            $pending = $this->applyStringOption($pending, 'description');
            $pending = $this->applyStringOption($pending, 'reference');

            $result = $pending->send();
        } catch (ConfigurationException | ViewMendException $exception) {
            $this->components->error(sprintf(
                'ViewMend deployment delivery failed: %s',
                $exception->getMessage(),
            ));

            return self::FAILURE;
        }

        $status = $result->duplicate ? 'duplicate' : 'accepted';
        $this->components->info(sprintf(
            'ViewMend deployment %s for [%s].',
            $status,
            $eventId,
        ));
        $this->line(sprintf(
            'Delivery: %s | ViewMend event: %s | Queue status: %s',
            $result->deliveryId->value,
            $result->eventId->value,
            $result->queueStatus->value,
        ));

        return self::SUCCESS;
    }

    private function requiredOption(string $name, string $message): ?string
    {
        $value = $this->option($name);
        if (!is_string($value) || trim($value) === '') {
            $this->components->error($message);

            return null;
        }

        return $value;
    }

    private function optionalStringOption(string $name): ?string
    {
        $value = $this->option($name);
        if ($value === null || $value === false || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('The --%s option must be a string.', $name));
        }

        return $value;
    }

    private function applyStringOption(PendingEvent $pending, string $name): PendingEvent
    {
        $value = $this->optionalStringOption($name);
        if ($value === null) {
            return $pending;
        }

        return match ($name) {
            'site' => $pending->site($value),
            'environment' => $pending->environment($value),
            'description' => $pending->description($value),
            'reference' => $pending->reference($value),
            default => throw new \LogicException(sprintf('Unsupported deployment option [%s].', $name)),
        };
    }

    /** @return list<string> */
    private function pageOptions(): array
    {
        $pages = $this->option('page');
        foreach ($pages as $page) {
            if (!is_string($page)) {
                throw new \InvalidArgumentException('Each --page option must be a string.');
            }
        }

        return array_values($pages);
    }
}
